<?php

namespace Tests\Feature;

use App\Enums\TrendGranularity;
use App\Models\User;
use App\Services\SpendingTrend;
use App\Support\CalendarOptions;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Month and anchor come off the query string, so they are the one input a user
 * can hand-edit without a form in the way. Both resolvers exist to absorb junk
 * — "a junk value navigates somewhere sensible rather than 500ing" — and both
 * used to fall over on the two shapes junk actually arrives in: an array, and a
 * month that overflows into the next year.
 */
class CalendarInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /**
     * PHP arrays are the free-est thing to send: ?month[]=1 costs nothing and
     * used to be a TypeError before the fallback could run.
     */
    #[DataProvider('arrayQueryStrings')]
    public function test_an_array_query_param_does_not_crash_the_page(string $url): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($url)->assertOk();
    }

    public static function arrayQueryStrings(): array
    {
        return [
            'dashboard budget month' => ['/dashboard?budget_month[]=1'],
            'dashboard breakdown month' => ['/dashboard?breakdown_month[]=1'],
            'dashboard trend anchor' => ['/dashboard?at[]=1'],
            'budgets month' => ['/budgets?month[]=1'],
            'reports anchor' => ['/reports?at[]=1'],
        ];
    }

    public function test_resolve_month_falls_back_on_a_non_string(): void
    {
        $this->assertTrue(
            CalendarOptions::resolveMonth(['nope'])->equalTo(CarbonImmutable::now()->startOfMonth()),
        );
    }

    /**
     * Carbon rolls an overflow forward instead of throwing, so '2025-13' became
     * January 2026 — a real-looking report for a month nobody asked for.
     */
    #[DataProvider('overflowingAnchors')]
    public function test_an_overflowing_anchor_falls_back_to_now(TrendGranularity $granularity, string $value): void
    {
        $now = CarbonImmutable::parse('2026-07-18');

        $anchor = app(SpendingTrend::class)->resolveAnchor($granularity, $value, $now);

        $this->assertTrue(
            $anchor->equalTo($now),
            "'{$value}' resolved to {$anchor->toDateString()} instead of falling back.",
        );
    }

    public static function overflowingAnchors(): array
    {
        return [
            'month 13' => [TrendGranularity::Month, '2025-13'],
            'month 00' => [TrendGranularity::Month, '2026-00'],
            'day 32' => [TrendGranularity::Week, '2026-07-32'],
        ];
    }

    /** A well-formed anchor still resolves, so the guard is not just refusing everything. */
    public function test_a_valid_anchor_still_resolves(): void
    {
        $now = CarbonImmutable::parse('2026-07-18');

        $anchor = app(SpendingTrend::class)->resolveAnchor(TrendGranularity::Month, '2026-03', $now);

        $this->assertSame('2026-03', $anchor->format('Y-m'));
    }
}
