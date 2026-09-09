<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Savings as a monthly plan: what was meant to go aside this month, what
 * actually did, and the all-time balance the two sit on top of.
 */
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
    private function planPayload(array $overrides = []): array
    {
        return [
            'month' => '2026-09',
            'amount' => '100',
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

    public function test_the_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/savings')->assertUnauthorized();
        $this->getJson('/api/v1/savings/summary')->assertUnauthorized();
        $this->getJson('/api/v1/savings/plan')->assertUnauthorized();
    }

    public function test_setting_a_plan_twice_updates_the_same_row(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $created = $this->postJson('/api/v1/savings/plan', $this->planPayload())
            ->assertCreated()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.amount', '100.00');

        // The same (user, month) slot, so this is a 200 on the row that is
        // already there rather than a second plan for September.
        $this->postJson('/api/v1/savings/plan', $this->planPayload(['amount' => '250']))
            ->assertOk()
            ->assertJsonPath('data.uuid', $created->json('data.uuid'))
            ->assertJsonPath('data.amount', '250.00');

        $this->assertSame(1, SavingsPlan::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('savings_plans', [
            'user_id' => $user->id,
            'month' => '2026-09-01',
            'amount' => 250,
        ]);
    }

    public function test_a_riel_plan_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/plan', $this->planPayload(['amount' => '400000', 'currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '100.00');
    }

    public function test_a_full_date_is_not_a_month(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/plan', $this->planPayload(['month' => '2026-09-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    }

    public function test_the_plan_endpoint_returns_the_month_or_null(): void
    {
        $user = User::factory()->create();
        SavingsPlan::factory()->for($user)->forMonth('2026-09')->create(['amount' => 100]);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->getJson('/api/v1/savings/plan?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.amount', '100.00');

        // A month with no plan is null, which is not the same as "0.00".
        $this->getJson('/api/v1/savings/plan?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_clearing_a_plan_leaves_the_money_alone(): void
    {
        $user = User::factory()->create();
        $plan = SavingsPlan::factory()->for($user)->forMonth('2026-09')->create();
        SavingsEntry::factory()->for($user)->create(['amount' => 60, 'saved_on' => '2026-09-05']);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->deleteJson("/api/v1/savings/plan/{$plan->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('savings_plans', ['id' => $plan->id]);
        $this->assertSame(1, SavingsEntry::where('user_id', $user->id)->count());
    }

    public function test_summary_reports_the_month_against_the_plan_and_the_all_time_balance(): void
    {
        $user = User::factory()->create();
        SavingsPlan::factory()->for($user)->forMonth('2026-09')->create(['amount' => 100]);
        SavingsEntry::factory()->for($user)->create(['amount' => 1180, 'saved_on' => '2026-08-20']);
        SavingsEntry::factory()->for($user)->create(['amount' => 80, 'saved_on' => '2026-09-02']);
        SavingsEntry::factory()->for($user)->withdrawal(20)->create(['saved_on' => '2026-09-04']);
        // Someone else's ledger does not count.
        SavingsEntry::factory()->create(['amount' => 999, 'saved_on' => '2026-09-01']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson('/api/v1/savings/summary?month=2026-09')->assertOk();

        $this->assertSame('2026-09', $response->json('data.month'));
        $this->assertSame('100.00', $response->json('data.planned'));
        // 80 - 20, this month only.
        $this->assertSame('60.00', $response->json('data.saved_this_month'));
        $this->assertSame('40.00', $response->json('data.remaining'));
        $this->assertSame(60, $response->json('data.percent'));
        $this->assertSame(60, $response->json('data.percent_raw'));
        $this->assertSame('ok', $response->json('data.status'));
        // 1180 + 80 - 20, every month.
        $this->assertSame('1240.00', $response->json('data.total_saved'));
        $this->assertSame(2, $response->json('data.entries_count'));
    }

    public function test_a_month_with_no_plan_reports_zeros_without_erroring(): void
    {
        $user = User::factory()->create();
        SavingsEntry::factory()->for($user)->create(['amount' => 50, 'saved_on' => '2026-09-03']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->getJson('/api/v1/savings/summary?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.planned', '0.00')
            ->assertJsonPath('data.saved_this_month', '50.00')
            // Nothing to measure against, so no progress and nothing to go.
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.percent', 0)
            ->assertJsonPath('data.percent_raw', 0)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.total_saved', '50.00');
    }

    public function test_a_fresh_account_summarises_to_all_zeros(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->getJson('/api/v1/savings/summary')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.planned', '0.00')
            ->assertJsonPath('data.saved_this_month', '0.00')
            ->assertJsonPath('data.total_saved', '0.00')
            ->assertJsonPath('data.entries_count', 0);
    }

    #[DataProvider('statusThresholds')]
    public function test_percent_and_status_follow_the_thresholds(float $saved, int $percent, int $raw, string $status): void
    {
        $user = User::factory()->create();
        SavingsPlan::factory()->for($user)->forMonth('2026-09')->create(['amount' => 100]);
        SavingsEntry::factory()->for($user)->create(['amount' => $saved, 'saved_on' => '2026-09-05']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->getJson('/api/v1/savings/summary?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.percent', $percent)
            ->assertJsonPath('data.percent_raw', $raw)
            ->assertJsonPath('data.status', $status);
    }

    /** @return array<string, array{float, int, int, string}> */
    public static function statusThresholds(): array
    {
        return [
            'under' => [60, 60, 60, 'ok'],
            'just under close' => [79.4, 79, 79, 'ok'],
            'close' => [80, 80, 80, 'close'],
            'met' => [100, 100, 100, 'met'],
            // The bar is capped; percent_raw keeps the truth.
            'over' => [150, 100, 150, 'met'],
        ];
    }

    public function test_index_returns_only_the_months_entries_newest_first(): void
    {
        $user = User::factory()->create();
        SavingsEntry::factory()->for($user)->create(['amount' => 10, 'saved_on' => '2026-08-31']);
        SavingsEntry::factory()->for($user)->create(['amount' => 20, 'saved_on' => '2026-09-01']);
        SavingsEntry::factory()->for($user)->create(['amount' => 30, 'saved_on' => '2026-09-07']);
        SavingsEntry::factory()->for($user)->create(['amount' => 40, 'saved_on' => '2026-10-01']);
        SavingsEntry::factory()->create(['amount' => 999, 'saved_on' => '2026-09-03']);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $response = $this->getJson('/api/v1/savings?month=2026-09')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('2026-09-07', $response->json('data.0.saved_on'));
        $this->assertSame('30.00', $response->json('data.0.amount'));
        $this->assertSame('2026-09-01', $response->json('data.1.saved_on'));

        // August is its own month, and no month at all means the current one.
        $this->getJson('/api/v1/savings?month=2026-08')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/savings')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_deposits_and_withdrawals_move_the_balance(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value, TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('deposit', '150'))
            ->assertCreated()
            ->assertJsonPath('data.type', 'deposit')
            ->assertJsonPath('data.amount', '150.00');

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('withdraw', '50', ['note' => 'Rainy day']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'withdraw')
            // Always the absolute value on the wire; the sign is storage.
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.note', 'Rainy day');

        $this->assertDatabaseHas('savings_entries', ['user_id' => $user->id, 'amount' => -50]);

        $this->getJson('/api/v1/savings/summary?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.saved_this_month', '100.00')
            ->assertJsonPath('data.total_saved', '100.00');
    }

    public function test_a_deposit_in_riel_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('deposit', '40000', ['currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '10.00');
    }

    public function test_a_withdrawal_is_capped_by_the_all_time_balance_not_the_month(): void
    {
        $user = User::factory()->create();
        // Saved in August; still there to take out in September.
        SavingsEntry::factory()->for($user)->create(['amount' => 40, 'saved_on' => '2026-08-10']);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('withdraw', '40.01'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You cannot withdraw more than is saved.');

        // The month's own figure is 0, and yet exactly what is banked can come out.
        $this->postJson('/api/v1/savings/entries', $this->entryPayload('withdraw', '40'))
            ->assertCreated();

        $this->assertSame(2, SavingsEntry::where('user_id', $user->id)->count());
    }

    public function test_a_future_dated_entry_and_a_bad_type_are_rejected(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('deposit', '10', ['saved_on' => '2026-09-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('saved_on');

        $this->postJson('/api/v1/savings/entries', $this->entryPayload('transfer', '10'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_an_entry_can_be_edited_and_deleted(): void
    {
        $user = User::factory()->create();
        // Something else in the ledger, so turning the line below into a
        // withdrawal has a balance to come out of.
        SavingsEntry::factory()->for($user)->create(['amount' => 100, 'saved_on' => '2026-09-01']);
        $entry = SavingsEntry::factory()->for($user)->create(['amount' => 60, 'saved_on' => '2026-09-02']);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->patchJson("/api/v1/savings/entries/{$entry->uuid}", $this->entryPayload('withdraw', '20', [
            'saved_on' => '2026-09-06',
            'note' => 'Changed my mind',
        ]))
            ->assertOk()
            ->assertJsonPath('data.type', 'withdraw')
            ->assertJsonPath('data.amount', '20.00')
            ->assertJsonPath('data.saved_on', '2026-09-06')
            ->assertJsonPath('data.note', 'Changed my mind');

        $this->assertDatabaseHas('savings_entries', ['id' => $entry->id, 'amount' => -20]);

        $this->deleteJson("/api/v1/savings/entries/{$entry->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('savings_entries', ['id' => $entry->id]);
    }

    public function test_an_edit_cannot_withdraw_more_than_the_rest_of_the_balance(): void
    {
        $user = User::factory()->create();
        SavingsEntry::factory()->for($user)->create(['amount' => 100, 'saved_on' => '2026-09-01']);
        $withdrawal = SavingsEntry::factory()->for($user)->withdrawal(20)->create(['saved_on' => '2026-09-02']);

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        // The row being edited is not part of its own ceiling: without it the
        // balance is 100, so 120 is too much and 100 is exactly enough.
        $this->patchJson("/api/v1/savings/entries/{$withdrawal->uuid}", $this->entryPayload('withdraw', '120'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'You cannot withdraw more than is saved.');

        $this->patchJson("/api/v1/savings/entries/{$withdrawal->uuid}", $this->entryPayload('withdraw', '100'))
            ->assertOk();

        $this->assertDatabaseHas('savings_entries', ['id' => $withdrawal->id, 'amount' => -100]);
    }

    public function test_someone_elses_plan_or_entry_is_forbidden(): void
    {
        $user = User::factory()->create();
        $theirPlan = SavingsPlan::factory()->create();
        $theirEntry = SavingsEntry::factory()->create(['amount' => 25]);

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value, TokenAbility::SavingsWrite->value]);

        $this->deleteJson("/api/v1/savings/plan/{$theirPlan->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->patchJson("/api/v1/savings/entries/{$theirEntry->uuid}", $this->entryPayload('deposit', '10'))
            ->assertForbidden();
        $this->deleteJson("/api/v1/savings/entries/{$theirEntry->uuid}")->assertForbidden();

        $this->assertDatabaseHas('savings_plans', ['id' => $theirPlan->id]);
        $this->assertDatabaseHas('savings_entries', ['id' => $theirEntry->id]);
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $user = User::factory()->create();
        $plan = SavingsPlan::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsRead->value]);

        $this->postJson('/api/v1/savings/plan', $this->planPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
        $this->deleteJson("/api/v1/savings/plan/{$plan->uuid}")->assertForbidden();
        $this->postJson('/api/v1/savings/entries', $this->entryPayload('deposit', '10'))->assertForbidden();

        $this->assertSame(0, SavingsEntry::count());
    }

    public function test_a_write_only_token_cannot_read(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::SavingsWrite->value]);

        $this->getJson('/api/v1/savings')->assertForbidden();
        $this->getJson('/api/v1/savings/summary')->assertForbidden();
        $this->getJson('/api/v1/savings/plan')->assertForbidden();
    }
}
