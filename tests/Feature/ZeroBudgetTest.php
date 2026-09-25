<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A budget of exactly $0 is a real budget.
 *
 * Currency::minimumInput() allows it for dollars on purpose — "$0 is a
 * deliberate 'nothing budgeted for this' rather than a typo" — so spending
 * against one is over budget, and must not read as though no budget were set.
 * The two states are already distinguishable in the payload (a null budget has
 * a null `remaining`, a $0 budget has a real one), which is what made the
 * collapse to 'none' visible.
 */
class ZeroBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH = '2026-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::MONTH.'-01');
    }

    private function overall(User $user): array
    {
        return app(BudgetSummary::class)->forMonth($user, $this->month())['overall'];
    }

    private function spend(User $user, float $amount): void
    {
        Expense::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::factory()->create()->id,
            'price' => $amount,
            'spent_on' => self::MONTH.'-10',
        ]);
    }

    private function budget(User $user, float $amount): void
    {
        Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => null,
            'month' => self::MONTH.'-01',
            'amount' => $amount,
        ]);
    }

    public function test_spending_against_a_zero_budget_is_over_not_unbudgeted(): void
    {
        $user = $this->user();
        $this->budget($user, 0.0);
        $this->spend($user, 5.0);

        $overall = $this->overall($user);

        $this->assertSame('over', $overall['status']);
        // A full track, since every cent of it is beyond the budget.
        $this->assertSame(100, $overall['bar_percent']);
        // "x% of nothing" has no value to report, so the ratio stays absent and
        // the status carries the meaning.
        $this->assertNull($overall['percent']);
    }

    public function test_a_zero_budget_with_no_spending_is_met_not_unbudgeted(): void
    {
        $user = $this->user();
        $this->budget($user, 0.0);

        $overall = $this->overall($user);

        $this->assertSame('ok', $overall['status']);
        $this->assertSame(0, $overall['percent']);
        $this->assertSame(0, $overall['bar_percent']);
    }

    public function test_no_budget_at_all_still_reads_as_none(): void
    {
        $user = $this->user();
        $this->spend($user, 5.0);

        $overall = $this->overall($user);

        $this->assertSame('none', $overall['status']);
        $this->assertNull($overall['percent']);
        $this->assertNull($overall['budget']);
        $this->assertNull($overall['remaining']);
    }

    public function test_the_overspend_banner_fires_for_a_zero_budget(): void
    {
        $user = $this->user();
        $this->budget($user, 0.0);
        $this->spend($user, 5.0);

        $overspend = app(BudgetSummary::class)->overspendFor($user, $this->month());

        $this->assertNotNull($overspend);
        $this->assertSame(-5.0, $overspend['remaining']);
    }
}
