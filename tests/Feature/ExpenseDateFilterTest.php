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
 * The Expenses page date filters: ?month=MM and ?year=YYYY.
 *
 * They are two independent controls rather than one period picker — a year alone
 * shows that whole year, a month alone shows that month in every year, and the
 * two together pin a single month. Both are query strings rather than a form, so
 * anything unparseable is ignored rather than raising.
 */
class ExpenseDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed, so "the current year" in yearOptions is deterministic.
        $this->travelTo(CarbonImmutable::parse('2026-07-15'));

        $this->user = User::factory()->create();
        $this->user->applyRole(RoleName::User);

        $this->expense('jul-2026', '2026-07-15');
        $this->expense('mar-2026', '2026-03-10');
        $this->expense('jul-2025', '2025-07-04');
        $this->expense('nov-2025', '2025-11-01');
    }

    private function expense(string $name, string $date): void
    {
        Expense::factory()->for($this->user)->create([
            'item' => ['en' => $name],
            'spent_on' => $date,
        ]);
    }

    /** @return array<string, mixed> the Inertia props off the rendered page */
    private function props(TestResponse $response): array
    {
        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];
    }

    /** @return array<int, string> item names across the grouped days */
    private function items(TestResponse $response): array
    {
        return collect($this->props($response)['days'])
            ->flatMap(fn (array $day) => $day['expenses'])
            ->pluck('item')
            ->all();
    }

    /** @param  array<string, string>  $query */
    private function visit(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->get(route('expenses.index', $query));
    }

    public function test_no_filter_shows_every_expense(): void
    {
        $this->assertEqualsCanonicalizing(
            ['jul-2026', 'mar-2026', 'jul-2025', 'nov-2025'],
            $this->items($this->visit()),
        );
    }

    /** A year alone spans that whole year. */
    public function test_year_alone_shows_that_year(): void
    {
        $this->assertEqualsCanonicalizing(
            ['jul-2025', 'nov-2025'],
            $this->items($this->visit(['year' => '2025'])),
        );
    }

    /**
     * A month alone deliberately crosses years — this is the behaviour that
     * separates two controls from a single period picker.
     */
    public function test_month_alone_shows_that_month_in_every_year(): void
    {
        $this->assertEqualsCanonicalizing(
            ['jul-2026', 'jul-2025'],
            $this->items($this->visit(['month' => '07'])),
        );
    }

    public function test_month_and_year_together_pin_one_month(): void
    {
        $this->assertSame(
            ['jul-2025'],
            $this->items($this->visit(['month' => '07', 'year' => '2025'])),
        );
    }

    /**
     * Junk is ignored rather than 500ing, and the payload echoes '' so the
     * select does not try to show a bogus value.
     */
    public function test_a_junk_month_falls_back_to_every_month(): void
    {
        $response = $this->visit(['month' => '13']);

        $response->assertOk();
        $this->assertCount(4, $this->items($response));
        $this->assertSame('', $this->props($response)['month']);
    }

    public function test_a_junk_year_falls_back_to_every_year(): void
    {
        $response = $this->visit(['year' => 'decade']);

        $response->assertOk();
        $this->assertCount(4, $this->items($response));
        $this->assertSame('', $this->props($response)['year']);
    }

    public function test_the_active_filters_are_echoed_back_for_the_selects(): void
    {
        $props = $this->props($this->visit(['month' => '03', 'year' => '2026']));

        $this->assertSame('03', $props['month']);
        $this->assertSame('2026', $props['year']);
    }

    /**
     * The year list is drawn from the viewer's own rows rather than an arbitrary
     * range, newest first.
     */
    public function test_year_options_come_from_the_viewers_own_expenses(): void
    {
        $this->assertSame([2026, 2025], $this->props($this->visit())['years']);
    }
}
