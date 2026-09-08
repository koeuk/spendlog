<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TokenAbility;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // Pinned so "today" and "this month" are not whatever the clock says
        // when the suite runs — mid-month, so month totals include today.
        Carbon::setTestNow('2026-07-16 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_a_token_without_the_ability_is_refused(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_the_whole_home_screen_in_one_call(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'today' => ['date', 'total'],
                    'summary' => ['month', 'overall', 'categories'],
                    'breakdown',
                    'recent',
                    'income' => ['month', 'total'],
                    'balance',
                    'savings' => ['total_saved', 'total_target', 'percent', 'goals_count'],
                ],
            ]);
    }

    /**
     * Income and balance sit on the budget month, so what came in and what
     * went out are measured over the same days.
     */
    public function test_income_and_balance_follow_the_budget_month(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Income::factory()->for($user)->create(['amount' => 1200, 'received_on' => '2026-07-01']);
        Income::factory()->for($user)->create(['amount' => 900, 'received_on' => '2026-06-01']);
        Expense::factory()->for($user)->for($category)->create(['price' => 75, 'spent_on' => '2026-07-10']);
        Expense::factory()->for($user)->for($category)->create(['price' => 1000, 'spent_on' => '2026-06-10']);
        // Someone else's income does not count.
        Income::factory()->create(['amount' => 5000, 'received_on' => '2026-07-01']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.income.month', '2026-07')
            ->assertJsonPath('data.income.total', '1200.00')
            ->assertJsonPath('data.balance', '1125.00');

        // Pointing the budgets at June moves income and balance with them —
        // and a balance may be negative.
        $this->getJson('/api/v1/dashboard?budget_month=2026-06')
            ->assertOk()
            ->assertJsonPath('data.income.month', '2026-06')
            ->assertJsonPath('data.income.total', '900.00')
            ->assertJsonPath('data.balance', '-100.00');
    }

    public function test_savings_reports_the_standing_position_across_every_goal(): void
    {
        $user = User::factory()->create();
        $fund = SavingsGoal::factory()->for($user)->create(['target_amount' => 1000]);
        $trip = SavingsGoal::factory()->for($user)->create(['target_amount' => 500]);
        SavingsEntry::factory()->for($fund, 'goal')->create(['amount' => 300, 'saved_on' => '2026-01-10']);
        SavingsEntry::factory()->for($trip, 'goal')->create(['amount' => 50, 'saved_on' => '2026-07-10']);
        SavingsEntry::factory()->for($trip, 'goal')->withdrawal(30)->create(['saved_on' => '2026-07-12']);
        SavingsGoal::factory()->create(['target_amount' => 9999]);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.savings.total_saved', '320.00')
            ->assertJsonPath('data.savings.total_target', '1500.00')
            ->assertJsonPath('data.savings.percent', 21)
            ->assertJsonPath('data.savings.goals_count', 2);
    }

    public function test_income_and_savings_are_zero_for_a_fresh_account(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.income.total', '0.00')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.savings.total_saved', '0.00')
            ->assertJsonPath('data.savings.goals_count', 0);
    }

    public function test_today_total_counts_only_todays_spend(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Expense::factory()->for($user)->for($category)->create(['price' => 12.50, 'spent_on' => '2026-07-16']);
        Expense::factory()->for($user)->for($category)->create(['price' => 99, 'spent_on' => '2026-07-15']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('2026-07-16', $response->json('data.today.date'));
        $this->assertSame('12.50', $response->json('data.today.total'));
    }

    public function test_today_total_ignores_other_peoples_spend(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Expense::factory()->for($user)->for($category)->create(['price' => 10, 'spent_on' => '2026-07-16']);
        Expense::factory()->for($category)->create(['price' => 500, 'spent_on' => '2026-07-16']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today.total', '10.00');
    }

    public function test_breakdown_ranks_categories_by_spend_with_their_share(): void
    {
        $user = User::factory()->create();
        $food = Category::factory()->create(['name' => 'Food']);
        $transport = Category::factory()->create(['name' => 'Transport']);

        Expense::factory()->for($user)->for($food)->create(['price' => 75, 'spent_on' => '2026-07-10']);
        Expense::factory()->for($user)->for($transport)->create(['price' => 25, 'spent_on' => '2026-07-10']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        // Largest first.
        $this->assertSame('Food', $response->json('data.breakdown.0.name'));
        $this->assertSame('75.00', $response->json('data.breakdown.0.spent'));
        $this->assertSame('Transport', $response->json('data.breakdown.1.name'));

        // Loose comparison: share is numeric, and JSON renders 75.0 as 75, so
        // the PHP type on the way back depends on whether the value is round.
        $this->assertEquals(75, $response->json('data.breakdown.0.share'));
        $this->assertEquals(25, $response->json('data.breakdown.1.share'));
    }

    public function test_breakdown_is_empty_when_nothing_was_spent(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.breakdown', []);
    }

    public function test_recent_is_capped_and_newest_first(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        foreach (range(1, 10) as $day) {
            Expense::factory()->for($user)->for($category)->create([
                'item' => "Day {$day}",
                'spent_on' => '2026-07-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
            ]);
        }

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertCount(8, $response->json('data.recent'));
        $this->assertSame('Day 10', $response->json('data.recent.0.item'));
    }

    public function test_summary_money_is_a_string_like_every_other_endpoint(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Budget::factory()->for($user)->for($category)->create([
            'amount' => 200,
            'month' => '2026-07-01',
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'price' => 50,
            'spent_on' => '2026-07-10',
        ]);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame('50.00', $response->json('data.summary.overall.spent'));

        $row = collect($response->json('data.summary.categories'))
            ->firstWhere('uuid', $category->uuid);

        $this->assertSame('200.00', $row['budget']);
        $this->assertSame('150.00', $row['remaining']);
        // Percent stays numeric — it is a ratio, not money.
        $this->assertSame(25, $row['percent']);
    }

    public function test_no_budget_reports_null_rather_than_zero(): void
    {
        $user = User::factory()->create();
        Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertNull($response->json('data.summary.overall.budget'));
        $this->assertSame('none', $response->json('data.summary.overall.status'));
    }

    public function test_recent_expenses_expose_uuids_not_internal_ids(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $expense = Expense::factory()->for($user)->for($category)->create(['spent_on' => '2026-07-10']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame($expense->uuid, $response->json('data.recent.0.uuid'));
        $this->assertArrayNotHasKey('id', $response->json('data.recent.0'));
    }
}
