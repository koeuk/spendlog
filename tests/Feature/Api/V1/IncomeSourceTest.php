<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\SavingsEntry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The names an account files its income under: a catalogue of suggestions that
 * can be managed, kept deliberately separate from the free text an income row
 * carries. Renaming or removing one leaves the history alone unless asked.
 */
class IncomeSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(RoleName $role = RoleName::User): User
    {
        $user = User::factory()->create();
        $user->applyRole($role);

        return $user;
    }

    // ------------------------------------------------------------- reading

    public function test_index_lists_the_catalogue_with_what_is_filed_under_each(): void
    {
        $user = $this->user();
        IncomeSource::factory()->for($user)->named('Salary')->create();
        IncomeSource::factory()->for($user)->named('Freelance')->create();
        // Never used, and honestly zero rather than missing.
        IncomeSource::factory()->for($user)->named('Gift')->create();
        // Someone else's catalogue is not this one.
        IncomeSource::factory()->named('Salary')->create();

        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 1000]);
        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 200]);
        Income::factory()->for($user)->create(['source' => 'Freelance', 'amount' => 50]);
        // Someone else's income does not count towards this account's names.
        Income::factory()->create(['source' => 'Salary', 'amount' => 9999]);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $response = $this->getJson('/api/v1/income-sources')->assertOk()->assertJsonCount(3, 'data');

        // Busiest first.
        $this->assertSame(['Salary', 'Freelance', 'Gift'], array_column($response->json('data'), 'name'));
        $this->assertSame([2, 1, 0], array_column($response->json('data'), 'uses'));
        $this->assertSame(['1200.00', '50.00', '0.00'], array_column($response->json('data'), 'total'));
    }

    public function test_the_picker_offers_the_catalogue_rather_than_the_income(): void
    {
        $user = $this->user();
        IncomeSource::factory()->for($user)->named('Gift')->create();
        // Filed under a name nobody curated: the income keeps it, but a list
        // of what to offer is the catalogue's to answer.
        Income::factory()->for($user)->create(['source' => 'Ad hoc']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->getJson('/api/v1/incomes/sources')
            ->assertOk()
            ->assertExactJson(['data' => ['Gift']]);
    }

    // ------------------------------------------------------------- writing

    public function test_a_source_can_be_added_before_anything_uses_it(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/income-sources', ['name' => 'Consulting'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Consulting')
            ->assertJsonPath('data.uses', 0)
            ->assertJsonPath('data.total', '0.00');

        $this->assertDatabaseHas('income_sources', ['user_id' => $user->id, 'name' => 'Consulting']);
    }

    public function test_the_same_name_twice_is_a_422_but_another_account_may_hold_it(): void
    {
        $user = $this->user();
        IncomeSource::factory()->for($user)->named('Salary')->create();
        IncomeSource::factory()->named('Salary')->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/income-sources', ['name' => 'Salary'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_renaming_leaves_the_income_alone_by_default(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();
        $income = Income::factory()->for($user)->create(['source' => 'Salary']);

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->patchJson("/api/v1/income-sources/{$source->uuid}", ['name' => 'Wages'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Wages');

        // The suggestion changed; what was entered months ago did not.
        $this->assertSame('Salary', $income->fresh()->source);
    }

    public function test_renaming_carries_the_income_across_when_asked(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();
        $mine = Income::factory()->for($user)->create(['source' => 'Salary']);
        $other = Income::factory()->for($user)->create(['source' => 'Gift']);
        $theirs = Income::factory()->create(['source' => 'Salary']);

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->patchJson("/api/v1/income-sources/{$source->uuid}", ['name' => 'Wages', 'rewrite_incomes' => true])
            ->assertOk();

        $this->assertSame('Wages', $mine->fresh()->source);
        // Only the name that was renamed, and only this account's.
        $this->assertSame('Gift', $other->fresh()->source);
        $this->assertSame('Salary', $theirs->fresh()->source);
    }

    public function test_renaming_onto_a_name_already_held_is_refused_rather_than_merged(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();
        IncomeSource::factory()->for($user)->named('Wages')->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->patchJson("/api/v1/income-sources/{$source->uuid}", ['name' => 'Wages'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_source_may_keep_its_own_name_through_an_edit(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        // Unchanged, or only recapitalised: the row must not collide with
        // itself on the way through.
        $this->patchJson("/api/v1/income-sources/{$source->uuid}", ['name' => 'Salary'])->assertOk();
    }

    public function test_removing_a_source_keeps_the_income_filed_under_it(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();
        $income = Income::factory()->for($user)->create(['source' => 'Salary']);

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->deleteJson("/api/v1/income-sources/{$source->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('income_sources', ['id' => $source->id]);
        // A suggestion went away, not a record.
        $this->assertSame('Salary', $income->fresh()->source);
    }

    // ------------------------------------------------------------ learning

    public function test_logging_income_under_a_new_name_teaches_it_to_the_catalogue(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/incomes', [
            'source' => 'Consulting',
            'amount' => 500,
            'received_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('income_sources', ['user_id' => $user->id, 'name' => 'Consulting']);
    }

    public function test_a_deposit_named_from_the_savings_form_joins_the_catalogue_too(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', [
            'type' => 'deposit',
            'amount' => 40,
            'saved_on' => now()->toDateString(),
            'source' => 'Bonus',
        ])->assertCreated();

        $this->assertDatabaseHas('income_sources', ['user_id' => $user->id, 'name' => 'Bonus']);
    }

    public function test_learning_a_name_is_not_logged_as_something_the_person_did(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Income::factory()->for($user)->create(['source' => 'Salary']);
        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/incomes', [
            'source' => 'Consulting',
            'amount' => 500,
            'received_on' => now()->toDateString(),
        ])->assertCreated();

        // The income is a thing someone chose to do; the name tagging along
        // behind it is not, and a log full of those is a log nobody reads.
        $this->assertDatabaseMissing('activity_logs', ['subject_type' => IncomeSource::class]);
    }

    // ---------------------------------------------------------- permissions

    public function test_someone_elses_catalogue_is_not_editable(): void
    {
        $user = $this->user();
        $theirs = IncomeSource::factory()->named('Salary')->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->patchJson("/api/v1/income-sources/{$theirs->uuid}", ['name' => 'Wages'])->assertForbidden();
        $this->deleteJson("/api/v1/income-sources/{$theirs->uuid}")->assertForbidden();
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $user = $this->user();
        $source = IncomeSource::factory()->for($user)->named('Salary')->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->postJson('/api/v1/income-sources', ['name' => 'Gift'])->assertForbidden();
        $this->patchJson("/api/v1/income-sources/{$source->uuid}", ['name' => 'Wages'])->assertForbidden();
        $this->deleteJson("/api/v1/income-sources/{$source->uuid}")->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/income-sources')->assertUnauthorized();
    }

    public function test_a_deposit_keeps_the_savings_entry_it_came_with(): void
    {
        // Guards the remember() side effect: it must not disturb the row it
        // rode in on.
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', [
            'type' => 'deposit',
            'amount' => 40,
            'saved_on' => now()->toDateString(),
            'source' => 'Bonus',
        ])->assertCreated()->assertJsonPath('data.source', 'Bonus');

        $this->assertSame(1, SavingsEntry::query()->where('user_id', $user->id)->count());
    }
}
