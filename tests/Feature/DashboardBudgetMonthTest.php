<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The dashboard's Budgets card: ?budget_month=YYYY-MM picks which month's
 * budgets to show.
 *
 * A month rather than a week/month/year span, because a budget row IS a monthly
 * amount — see App\Models\Budget. Independent of the breakdown card's period,
 * and of the page heading, which stays on the real current month.
 */
class DashboardBudgetMonthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-15'));

        $this->user = User::factory()->create();
        $this->user->applyRole(RoleName::User);

        $this->category = Category::factory()->create(['name' => ['en' => 'Coffee']]);

        // Different budgets in two months, so following the filter is visible.
        $this->budget('2026-07-01', 100);
        $this->budget('2026-06-01', 250);
    }

    private function budget(string $month, float $amount): void
    {
        $this->user->budgets()->create([
            'category_id' => $this->category->id,
            'month' => $month,
            'amount' => $amount,
        ]);
    }

    private function visit(?string $month = null): TestResponse
    {
        return $this->actingAs($this->user)->get(
            $month ? route('dashboard', ['budget_month' => $month]) : route('dashboard'),
        );
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /**
     * Read from `budgets`, the card's own prop — not `summary`, which belongs to
     * the hero and is anchored to the real current month.
     */
    private function budgetFor(TestResponse $response): float
    {
        return (float) collect($this->props($response)['budgets'])
            ->firstWhere('name', 'Coffee')['budget'];
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $response = $this->visit();

        $this->assertSame('2026-07', $this->props($response)['budget_month']);
        $this->assertSame(100.0, $this->budgetFor($response));
    }

    public function test_it_follows_the_chosen_month(): void
    {
        $response = $this->visit('2026-06');

        $this->assertSame('2026-06', $this->props($response)['budget_month']);
        $this->assertSame(250.0, $this->budgetFor($response));
    }

    /**
     * The hero card is labelled "this month", so it must stay on the real one.
     *
     * It used to read from the same `summary` this card did, so choosing June
     * here silently rewrote the hero to June — the heading still said July while
     * the figure beneath it reported another month.
     */
    public function test_choosing_a_month_does_not_move_the_hero(): void
    {
        $props = $this->props($this->visit('2026-06'));

        $this->assertSame('2026-07', $props['summary']['month']);
    }

    public function test_a_junk_month_falls_back_to_the_current_one(): void
    {
        foreach (['banana', '2026-13', '2026', ''] as $junk) {
            $response = $this->visit($junk);

            $this->assertSame(
                '2026-07',
                $this->props($response)['budget_month'],
                "junk value [{$junk}] should fall back",
            );
        }
    }

    public function test_the_page_heading_stays_on_the_real_current_month(): void
    {
        // Retitling the whole dashboard to a month whose spend it is not showing
        // would be a lie, so the heading does not follow this filter.
        $props = $this->props($this->visit('2026-06'));

        $this->assertSame('2026-07', $props['current_month']);
    }

    public function test_the_breakdown_does_not_follow_the_budget_month(): void
    {
        // The two cards are filtered independently.
        $props = $this->props($this->visit('2026-06'));

        $this->assertSame('2026-07', $props['breakdown_month']);
    }

    public function test_the_year_options_include_the_month_being_viewed(): void
    {
        // Someone can navigate to a year with no data; the select still has to
        // be able to show where they are.
        $props = $this->props($this->visit('2024-03'));

        $this->assertContains(2024, $props['budget_years']);
        $this->assertContains(2026, $props['budget_years']);
    }

    public function test_the_month_options_cover_the_whole_year(): void
    {
        $months = $this->props($this->visit())['budget_months'];

        $this->assertCount(12, $months);
        $this->assertSame('01', $months[0]['value']);
        $this->assertSame('12', $months[11]['value']);
    }
}
