<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Expenses page date filter: ?period=week|month|year narrows the list to the
 * current period, computed server-side off "now".
 */
class ExpensePeriodFilterTest extends TestCase
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

        $this->expense('this-week', '2026-07-15');       // in the week (Mon 13–Sun 19)
        $this->expense('this-month', '2026-07-02');      // this month, earlier week
        $this->expense('this-year', '2026-03-10');       // this year, earlier month
        $this->expense('last-year', '2025-11-01');       // outside the year
    }

    private function expense(string $name, string $date): void
    {
        Expense::factory()->for($this->user)->create([
            'item' => ['en' => $name],
            'spent_on' => $date,
        ]);
    }

    /** @return array<int, string> item names across the grouped days */
    private function items(TestResponse $response): array
    {
        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];

        return collect($props['days'])
            ->flatMap(fn (array $day) => $day['expenses'])
            ->pluck('item')
            ->all();
    }

    private function visit(string $period = ''): TestResponse
    {
        $query = $period === '' ? [] : ['period' => $period];

        return $this->actingAs($this->user)->get(route('expenses.index', $query));
    }

    public function test_no_period_shows_every_expense(): void
    {
        $items = $this->items($this->visit());

        $this->assertEqualsCanonicalizing(
            ['this-week', 'this-month', 'this-year', 'last-year'],
            $items,
        );
    }

    public function test_week_shows_only_this_week(): void
    {
        $this->assertSame(['this-week'], $this->items($this->visit('week')));
    }

    public function test_month_shows_only_this_month(): void
    {
        $this->assertEqualsCanonicalizing(
            ['this-week', 'this-month'],
            $this->items($this->visit('month')),
        );
    }

    public function test_year_shows_only_this_year(): void
    {
        $this->assertEqualsCanonicalizing(
            ['this-week', 'this-month', 'this-year'],
            $this->items($this->visit('year')),
        );
    }

    public function test_a_junk_period_falls_back_to_all(): void
    {
        $response = $this->visit('decade');

        $response->assertOk();
        $this->assertCount(4, $this->items($response));

        // And the payload reports 'all' so the dropdown does not show a bogus value.
        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];
        $this->assertSame('all', $props['period']);
    }

    public function test_the_active_period_is_echoed_back_for_the_dropdown(): void
    {
        $response = $this->visit('month');

        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];

        $this->assertSame('month', $props['period']);
    }
}
