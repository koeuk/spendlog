<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The over-budget bar rides every authenticated page, so what it says has to
 * agree with the Budgets page it links to, and it must not appear for anyone
 * who cannot act on it.
 */
class OverBudgetBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        CarbonImmutable::setTestNow('2026-07-17 09:00:00');
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    private function spend(User $user, float $price, string $on = '2026-07-05'): void
    {
        $user->expenses()->create([
            'category_id' => Category::factory()->create()->id,
            'spent_on' => $on,
            'price' => $price,
            'item' => ['en' => 'test'],
        ]);
    }

    private function budget(User $user, float $amount, string $month = '2026-07-01'): void
    {
        $user->budgets()->create([
            'category_id' => null,
            'month' => $month,
            'amount' => $amount,
        ]);
    }

    private function sharedProp(User $user): mixed
    {
        return $this->actingAs($user)
            ->get(route('dashboard'))
            ->viewData('page')['props']['over_budget'];
    }

    public function test_it_reports_the_overspend_when_the_month_is_over(): void
    {
        $user = $this->user();
        $this->budget($user, 100);
        $this->spend($user, 103);

        $over = $this->sharedProp($user);

        $this->assertNotNull($over);
        $this->assertSame(103.0, $over['spent']);
        $this->assertSame(100.0, $over['budget']);
        $this->assertSame(-3.0, $over['remaining']);
        $this->assertSame('2026-07', $over['month']);
    }

    public function test_it_says_nothing_when_the_spend_is_within_budget(): void
    {
        $user = $this->user();
        $this->budget($user, 200);
        $this->spend($user, 103);

        $this->assertNull($this->sharedProp($user));
    }

    public function test_it_says_nothing_when_no_overall_budget_is_set(): void
    {
        $user = $this->user();
        $this->spend($user, 103);

        $this->assertNull($this->sharedProp($user));
    }

    /**
     * Spending exactly your budget is not overspending. The rule is a rounded
     * percentage above 100, matching the Budgets page — if the two ever disagree
     * the bar fires while the page it links to says you are fine.
     */
    public function test_spending_exactly_the_budget_is_not_over(): void
    {
        $user = $this->user();
        $this->budget($user, 100);
        $this->spend($user, 100);

        $this->assertNull($this->sharedProp($user));
    }

    public function test_it_ignores_spend_from_other_months(): void
    {
        $user = $this->user();
        $this->budget($user, 100);
        $this->spend($user, 500, '2026-06-30');
        $this->spend($user, 10, '2026-08-01');

        $this->assertNull($this->sharedProp($user));
    }

    public function test_it_ignores_another_users_spend(): void
    {
        $user = $this->user();
        $this->budget($user, 100);

        $other = $this->user();
        $this->spend($other, 500);

        $this->assertNull($this->sharedProp($user));
    }

    /**
     * The bar links to the Budgets page. Someone who cannot open it would get a
     * warning with nowhere to go.
     */
    public function test_it_says_nothing_to_a_user_who_cannot_view_budgets(): void
    {
        $user = $this->user();
        $this->budget($user, 100);
        $this->spend($user, 103);

        $user->revokePermissionTo(Permission::BudgetsView->value);

        $this->assertNull($this->sharedProp($user->fresh()));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
