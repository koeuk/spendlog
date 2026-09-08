<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "today" is not whatever the clock says when the suite runs.
        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'source' => 'Salary',
            'amount' => '1200',
            'received_on' => '2026-09-01',
            ...$overrides,
        ];
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/incomes')->assertUnauthorized();
    }

    public function test_index_returns_only_the_callers_income_newest_first(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->create(['source' => 'Older', 'received_on' => '2026-08-01']);
        Income::factory()->for($user)->create(['source' => 'Newer', 'received_on' => '2026-09-01']);
        Income::factory()->create(['received_on' => '2026-09-05']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $response = $this->getJson('/api/v1/incomes')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame('Newer', $response->json('data.0.source'));
        $this->assertSame('Older', $response->json('data.1.source'));
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
    }

    public function test_sources_lists_the_callers_sources_most_used_first(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->count(2)->create(['source' => 'Freelance']);
        Income::factory()->for($user)->count(3)->create(['source' => 'Salary']);
        Income::factory()->for($user)->create(['source' => 'Bonus']);
        Income::factory()->create(['source' => 'Theirs']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->getJson('/api/v1/incomes/sources')
            ->assertOk()
            ->assertExactJson(['data' => ['Salary', 'Freelance', 'Bonus']]);
    }

    public function test_index_filters_by_source_and_date_range(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->create(['source' => 'Salary', 'received_on' => '2026-09-01']);
        Income::factory()->for($user)->create(['source' => 'Salary', 'received_on' => '2026-08-01']);
        Income::factory()->for($user)->create(['source' => 'Gift', 'received_on' => '2026-09-02']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->getJson('/api/v1/incomes?filter[source]=sal')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/incomes?filter[from]=2026-09-01&filter[to]=2026-09-30')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/incomes?filter[source]=sal&filter[from]=2026-09-01')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_read_only_token_cannot_record_income(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->postJson('/api/v1/incomes', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
    }

    public function test_store_records_income_for_the_token_owner(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $response = $this->postJson('/api/v1/incomes', $this->payload(['note' => 'September pay']))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['uuid', 'source', 'amount', 'received_on', 'note', 'created_at', 'updated_at']]);

        // Money is a string, formatted to cents.
        $this->assertSame('1200.00', $response->json('data.amount'));
        $this->assertSame('2026-09-01', $response->json('data.received_on'));
        $this->assertSame('September pay', $response->json('data.note'));

        $this->assertDatabaseHas('incomes', ['user_id' => $user->id, 'source' => 'Salary']);
    }

    public function test_a_riel_amount_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/incomes', $this->payload(['amount' => '20000', 'currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '5.00');

        $this->assertSame('5.0000', Income::sole()->amount);
    }

    public function test_store_rejects_a_future_date_and_a_zero_amount(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->postJson('/api/v1/incomes', $this->payload(['received_on' => '2026-09-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('received_on');

        $this->postJson('/api/v1/incomes', $this->payload(['amount' => '0']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_someone_elses_income_is_forbidden_unless_manage_all_is_held(): void
    {
        $user = User::factory()->create();
        $theirs = Income::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value, TokenAbility::IncomesWrite->value]);

        $this->getJson("/api/v1/incomes/{$theirs->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->patchJson("/api/v1/incomes/{$theirs->uuid}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/incomes/{$theirs->uuid}")->assertForbidden();

        // The permission, not the role: granting it to a plain user must work.
        $user->givePermissionTo(Permission::IncomesManageAll->value);

        $this->getJson("/api/v1/incomes/{$theirs->uuid}")->assertOk();
        $this->deleteJson("/api/v1/incomes/{$theirs->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('incomes', ['id' => $theirs->id]);
    }

    public function test_update_and_delete_own_income(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create(['note' => 'Old note']);

        Sanctum::actingAs($user, [TokenAbility::IncomesWrite->value]);

        $this->patchJson("/api/v1/incomes/{$income->uuid}", $this->payload(['source' => 'Freelance', 'amount' => '99.5']))
            ->assertOk()
            ->assertJsonPath('data.source', 'Freelance')
            ->assertJsonPath('data.amount', '99.50')
            // An omitted note clears the old one — PATCH takes the full shape.
            ->assertJsonPath('data.note', null);

        $this->deleteJson("/api/v1/incomes/{$income->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    }

    public function test_an_unknown_or_non_uuid_key_is_a_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->getJson('/api/v1/incomes/1')->assertNotFound();
        $this->getJson('/api/v1/incomes/0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a')->assertNotFound();
    }

    public function test_summary_totals_the_month_by_source_largest_first(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 1000, 'received_on' => '2026-09-01']);
        Income::factory()->for($user)->create(['source' => 'Freelance', 'amount' => 150, 'received_on' => '2026-09-03']);
        Income::factory()->for($user)->create(['source' => 'Freelance', 'amount' => 50, 'received_on' => '2026-09-05']);
        // Another month, and another person: neither counts.
        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 1000, 'received_on' => '2026-08-01']);
        Income::factory()->create(['source' => 'Salary', 'amount' => 5000, 'received_on' => '2026-09-01']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $response = $this->getJson('/api/v1/incomes/summary?month=2026-09')->assertOk();

        $this->assertSame('2026-09', $response->json('data.month'));
        $this->assertSame('1200.00', $response->json('data.total'));
        $this->assertSame(3, $response->json('data.count'));
        $this->assertSame([
            ['source' => 'Salary', 'total' => '1000.00'],
            ['source' => 'Freelance', 'total' => '200.00'],
        ], $response->json('data.by_source'));
    }

    public function test_summary_defaults_to_the_current_month(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->create(['amount' => 10, 'received_on' => '2026-09-02']);
        Income::factory()->for($user)->create(['amount' => 99, 'received_on' => '2026-08-02']);

        Sanctum::actingAs($user, [TokenAbility::IncomesRead->value]);

        $this->getJson('/api/v1/incomes/summary')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.total', '10.00')
            ->assertJsonPath('data.count', 1);
    }
}
