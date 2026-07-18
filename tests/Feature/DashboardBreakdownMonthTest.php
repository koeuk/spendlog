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
 * The dashboard's "Where it went" card: ?breakdown_month=YYYY-MM picks which
 * month to split.
 *
 * Its own month, independent of the Budgets card's and of the hero — pointing
 * one at March must not drag the others with it.
 */
class DashboardBreakdownMonthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-15'));

        $this->user = User::factory()->create();
        $this->user->applyRole(RoleName::User);

        $this->spend('july-big', '2026-07-02', 30);
        $this->spend('july-small', '2026-07-15', 10);
        $this->spend('march', '2026-03-10', 40);
    }

    private function spend(string $name, string $date, float $price): void
    {
        $category = Category::factory()->create(['name' => ['en' => $name]]);

        $this->user->expenses()->create([
            'category_id' => $category->id,
            'item' => ['en' => $name],
            'price' => $price,
            'spent_on' => $date,
        ]);
    }

    private function visit(?string $month = null): TestResponse
    {
        return $this->actingAs($this->user)->get(
            $month ? route('dashboard', ['breakdown_month' => $month]) : route('dashboard'),
        );
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return array<int, string> */
    private function names(TestResponse $response): array
    {
        return collect($this->props($response)['breakdown'])->pluck('name')->all();
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $response = $this->visit();

        $this->assertSame('2026-07', $this->props($response)['breakdown_month']);
        // Ranked by spend, largest first.
        $this->assertSame(['july-big', 'july-small'], $this->names($response));
    }

    public function test_it_follows_the_chosen_month(): void
    {
        $response = $this->visit('2026-03');

        $this->assertSame('2026-03', $this->props($response)['breakdown_month']);
        $this->assertSame(['march'], $this->names($response));
    }

    public function test_shares_are_computed_against_the_shown_month(): void
    {
        // July holds 30 + 10, so the shares are of 40 — not of every expense ever.
        $breakdown = collect($this->props($this->visit())['breakdown'])->keyBy('name');

        $this->assertSame(75.0, $breakdown['july-big']['share']);
        $this->assertSame(25.0, $breakdown['july-small']['share']);
    }

    public function test_an_empty_month_returns_nothing_rather_than_zero_rows(): void
    {
        // Every share would be a division by zero, and a list of 0% rows says
        // less than an empty state does.
        $this->assertSame([], $this->props($this->visit('2025-01'))['breakdown']);
    }

    public function test_a_junk_month_falls_back_to_the_current_one(): void
    {
        // '2026-13' is the interesting one: it matches the YYYY-MM shape, and
        // Carbon rolls it forward to 2027-01 rather than throwing.
        foreach (['banana', '2026-13', '2026', ''] as $junk) {
            $this->assertSame(
                '2026-07',
                $this->props($this->visit($junk))['breakdown_month'],
                "junk value [{$junk}] should fall back",
            );
        }
    }

    public function test_it_does_not_move_the_budgets_card_or_the_heading(): void
    {
        $props = $this->props($this->visit('2026-03'));

        $this->assertSame('2026-07', $props['budget_month']);
        $this->assertSame('2026-07', $props['current_month']);
    }

    public function test_the_options_cover_the_year_and_include_the_month_viewed(): void
    {
        $props = $this->props($this->visit('2024-03'));

        $this->assertCount(12, $props['breakdown_months']);
        $this->assertContains(2024, $props['breakdown_years']);
        $this->assertContains(2026, $props['breakdown_years']);
    }
}
