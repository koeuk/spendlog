<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function goalPayload(array $overrides = []): array
    {
        return [
            'name' => 'Emergency fund',
            'target_amount' => '500',
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function entryPayload(string $type, string $amount, array $overrides = []): array
    {
        return [
            'type' => $type,
            'amount' => $amount,
            'saved_on' => '2026-09-05',
            ...$overrides,
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/savings')->assertUnauthorized();
    }

    public function test_index_lists_the_callers_goals_with_their_progress(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 500]);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 100]);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 50]);
        SavingsEntry::factory()->for($goal, 'goal')->withdrawal(30)->create();
        SavingsGoal::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson('/api/v1/savings')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('500.00', $response->json('data.0.target_amount'));
        $this->assertSame('120.00', $response->json('data.0.saved'));
        $this->assertSame('380.00', $response->json('data.0.remaining'));
        $this->assertSame(24, $response->json('data.0.percent'));
        $this->assertFalse($response->json('data.0.reached'));
        // The ledger is only on the detail view.
        $this->assertArrayNotHasKey('entries', $response->json('data.0'));
    }

    public function test_store_creates_a_goal_with_the_default_colour(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $response = $this->postJson('/api/v1/savings', $this->goalPayload(['deadline' => '2027-01-01']))
            ->assertCreated();

        $this->assertSame('500.00', $response->json('data.target_amount'));
        $this->assertSame('0.00', $response->json('data.saved'));
        $this->assertSame('500.00', $response->json('data.remaining'));
        $this->assertSame(0, $response->json('data.percent'));
        $this->assertSame('2027-01-01', $response->json('data.deadline'));
        $this->assertSame('slate', $response->json('data.color'));

        $this->assertDatabaseHas('savings_goals', ['user_id' => $user->id, 'name' => 'Emergency fund']);
    }

    public function test_a_riel_target_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings', $this->goalPayload(['target_amount' => '2000000', 'currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.target_amount', '500.00');
    }

    public function test_a_new_goal_cannot_already_be_overdue_but_an_edited_one_may_be(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings', $this->goalPayload(['deadline' => '2026-09-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');

        $goal = SavingsGoal::factory()->for($user)->create(['color' => 'teal']);

        $this->patchJson("/api/v1/savings/{$goal->uuid}", $this->goalPayload(['name' => 'Renamed', 'deadline' => '2026-09-01']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.deadline', '2026-09-01')
            // An omitted colour on an edit leaves the existing one alone.
            ->assertJsonPath('data.color', 'teal');
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->postJson('/api/v1/savings', $this->goalPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('deposit', '10'))->assertForbidden();
    }

    public function test_someone_elses_goal_is_forbidden(): void
    {
        $user = User::factory()->create();
        $theirs = SavingsGoal::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value, TokenAbility::SavingsWrite->value]);

        $this->getJson("/api/v1/savings/{$theirs->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->patchJson("/api/v1/savings/{$theirs->uuid}", $this->goalPayload())->assertForbidden();
        $this->deleteJson("/api/v1/savings/{$theirs->uuid}")->assertForbidden();
        // Entries are authorised through their goal.
        $this->postJson("/api/v1/savings/{$theirs->uuid}/entries", $this->entryPayload('deposit', '10'))->assertForbidden();

        $this->assertSame(0, SavingsEntry::count());
    }

    public function test_deposits_and_withdrawals_move_the_balance(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 200]);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value, TokenAbility::SavingsWrite->value]);

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('deposit', '150'))
            ->assertCreated()
            ->assertJsonPath('data.type', 'deposit')
            ->assertJsonPath('data.amount', '150.00');

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('withdraw', '50', ['note' => 'Rainy day']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'withdraw')
            // Always the absolute value on the wire; the sign is storage.
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.note', 'Rainy day');

        $this->assertDatabaseHas('savings_entries', ['savings_goal_id' => $goal->id, 'user_id' => $user->id, 'amount' => -50]);

        $response = $this->getJson("/api/v1/savings/{$goal->uuid}")->assertOk();

        $this->assertSame('100.00', $response->json('data.saved'));
        $this->assertSame('100.00', $response->json('data.remaining'));
        $this->assertSame(50, $response->json('data.percent'));
    }

    public function test_withdrawing_more_than_is_saved_is_refused(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 40]);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('withdraw', '40.01'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You cannot withdraw more than is saved.');

        // Exactly what is there can come out.
        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('withdraw', '40'))
            ->assertCreated();

        $this->assertSame(2, SavingsEntry::count());
    }

    public function test_a_deposit_in_riel_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('deposit', '40000', ['currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '10.00');
    }

    public function test_a_future_dated_entry_and_a_bad_type_are_rejected(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('deposit', '10', ['saved_on' => '2026-09-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('saved_on');

        $this->postJson("/api/v1/savings/{$goal->uuid}/entries", $this->entryPayload('transfer', '10'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_show_carries_the_ledger_newest_first(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 100]);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 60, 'saved_on' => '2026-09-01']);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 40, 'saved_on' => '2026-09-05']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson("/api/v1/savings/{$goal->uuid}")->assertOk();

        $this->assertSame('2026-09-05', $response->json('data.entries.0.saved_on'));
        $this->assertSame('2026-09-01', $response->json('data.entries.1.saved_on'));
        $this->assertSame('100.00', $response->json('data.saved'));
        $this->assertSame('0.00', $response->json('data.remaining'));
        $this->assertSame(100, $response->json('data.percent'));
        $this->assertTrue($response->json('data.reached'));
    }

    public function test_an_entry_can_only_be_deleted_through_its_own_goal(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();
        $other = SavingsGoal::factory()->for($user)->create();
        $entry = SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 25]);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        // Right entry, wrong goal: scoped binding makes it a 404, not a hit.
        $this->deleteJson("/api/v1/savings/{$other->uuid}/entries/{$entry->uuid}")->assertNotFound();
        $this->assertDatabaseHas('savings_entries', ['id' => $entry->id]);

        $this->deleteJson("/api/v1/savings/{$goal->uuid}/entries/{$entry->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('savings_entries', ['id' => $entry->id]);
    }

    public function test_deleting_a_goal_takes_its_ledger_with_it(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create();
        SavingsEntry::factory()->for($goal, 'goal')->count(2)->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->deleteJson("/api/v1/savings/{$goal->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
        $this->assertSame(0, SavingsEntry::where('savings_goal_id', $goal->id)->count());
    }

    public function test_summary_totals_every_goal_and_the_months_deposits(): void
    {
        $user = User::factory()->create();
        $fund = SavingsGoal::factory()->for($user)->create(['target_amount' => 1000]);
        $trip = SavingsGoal::factory()->for($user)->create(['target_amount' => 500]);
        SavingsEntry::factory()->for($fund, 'goal')->create(['amount' => 270, 'saved_on' => '2026-08-20']);
        SavingsEntry::factory()->for($fund, 'goal')->create(['amount' => 80, 'saved_on' => '2026-09-02']);
        SavingsEntry::factory()->for($trip, 'goal')->withdrawal(30)->create(['saved_on' => '2026-09-04']);
        SavingsEntry::factory()->for($trip, 'goal')->create(['amount' => 30, 'saved_on' => '2026-08-01']);
        // Someone else's goal does not count.
        SavingsEntry::factory()->create(['amount' => 999, 'saved_on' => '2026-09-01']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson('/api/v1/savings/summary?month=2026-09')->assertOk();

        $this->assertSame('2026-09', $response->json('data.month'));
        // 270 + 80 + 30 - 30
        $this->assertSame('350.00', $response->json('data.total_saved'));
        $this->assertSame('1500.00', $response->json('data.total_target'));
        // round(350 / 1500 * 100)
        $this->assertSame(23, $response->json('data.percent'));
        $this->assertSame(2, $response->json('data.goals_count'));
        // 80 - 30, this month only.
        $this->assertSame('50.00', $response->json('data.saved_this_month'));
    }

    public function test_summary_with_no_goals_is_all_zeros(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->getJson('/api/v1/savings/summary')
            ->assertOk()
            ->assertJsonPath('data.total_saved', '0.00')
            ->assertJsonPath('data.total_target', '0.00')
            ->assertJsonPath('data.percent', 0)
            ->assertJsonPath('data.goals_count', 0)
            ->assertJsonPath('data.saved_this_month', '0.00');
    }

    public function test_percent_is_capped_at_100_when_over_saved(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 100]);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 150]);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson('/api/v1/savings')->assertOk();

        $this->assertSame(100, $response->json('data.0.percent'));
        $this->assertSame('0.00', $response->json('data.0.remaining'));
        $this->assertTrue($response->json('data.0.reached'));
    }
}
