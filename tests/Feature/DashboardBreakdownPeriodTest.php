<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The dashboard's "Where it went" card: ?breakdown=week|month|year narrows the
 * category split to the current period, computed server-side off "now".
 *
 * Independent of the Budgets card beside it, which stays monthly because a
 * budget is a monthly amount.
 */
class DashboardBreakdownPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed Wednesday, so week/month/year boundaries are deterministic.
        $this->travelTo(CarbonImmutable::parse('2026-07-15'));

        $this->user = User::factory()->create();
        $this->user->applyRole(RoleName::User);

        $this->expense('this-week', '2026-07-15', 10);   // in the week (Mon 13–Sun 19)
        $this->expense('this-month', '2026-07-02', 20);  // this month, earlier week
        $this->expense('this-year', '2026-03-10', 40);   // this year, earlier month
        $this->expense('last-year', '2025-11-01', 80);   // outside the year
    }

    private function expense(string $name, string $date, float $price): void
    {
        $category = Category::factory()->create(['name' => ['en' => $name]]);

        $this->user->expenses()->create([
            'category_id' => $category->id,
            'item' => ['en' => $name],
            'price' => $price,
            'spent_on' => $date,
        ]);
    }

    private function visit(string $period = ''): TestResponse
    {
        return $this->actingAs($this->user)->get(
            $period ? route('dashboard', ['breakdown' => $period]) : route('dashboard'),
        );
    }

    /** @return array<int, string> */
    private function categories(TestResponse $response): array
    {
        return collect($response->viewData('page')['props']['breakdown'])
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    public function test_week_shows_only_this_week(): void
    {
        $this->assertSame(['this-week'], $this->categories($this->visit('week')));
    }

    public function test_month_shows_this_month(): void
    {
        $this->assertSame(
            ['this-month', 'this-week'],
            $this->categories($this->visit('month')),
        );
    }

    public function test_year_shows_this_year(): void
    {
        $this->assertSame(
            ['this-month', 'this-week', 'this-year'],
            $this->categories($this->visit('year')),
        );
    }

    public function test_no_period_falls_back_to_the_month(): void
    {
        $response = $this->visit();

        $this->assertSame(['this-month', 'this-week'], $this->categories($response));
        $this->assertSame('month', $response->viewData('page')['props']['breakdown_period']);
    }

    public function test_a_junk_period_falls_back_to_the_month(): void
    {
        $response = $this->visit('fortnight');

        $this->assertSame(['this-month', 'this-week'], $this->categories($response));
        $this->assertSame('month', $response->viewData('page')['props']['breakdown_period']);
    }

    public function test_shares_are_computed_against_the_period_not_the_month(): void
    {
        // The year holds 10 + 20 + 40 = 70, so March's 40 is 57.1% of it — where
        // against the month it would be no share at all.
        $breakdown = collect($this->visit('year')->viewData('page')['props']['breakdown'])
            ->keyBy('name');

        $this->assertSame(57.1, $breakdown['this-year']['share']);
        $this->assertSame(28.6, $breakdown['this-month']['share']);
        $this->assertSame(14.3, $breakdown['this-week']['share']);
    }

    public function test_the_budgets_card_stays_monthly_whatever_the_breakdown_asks(): void
    {
        // The summary drives the Budgets card; it must not follow this picker.
        $summary = $this->visit('year')->viewData('page')['props']['summary'];

        $this->assertSame('2026-07', $summary['month']);
        $this->assertSame(30.0, $summary['overall']['spent']);
    }
}
