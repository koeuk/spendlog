<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "vs :period" figure on the Reports page.
 *
 * It used to sum the whole previous period and compare it against the elapsed
 * part of the current one, so on the 18th it weighed 18 days against 30 and
 * reported a fall that had not happened. The same function already truncates
 * for daily_average, with a comment explaining why; the comparison eleven lines
 * below never got the same treatment.
 */
class ReportComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function spendDaily(User $user, string $from, string $to, float $amount): void
    {
        $category = Category::factory()->create();

        for ($day = CarbonImmutable::parse($from); $day->lte(CarbonImmutable::parse($to)); $day = $day->addDay()) {
            Expense::factory()->for($user)->for($category)->create([
                'price' => $amount,
                'spent_on' => $day->toDateString(),
            ]);
        }
    }

    /** Flat spending must read as flat, not as a collapse. */
    public function test_a_flat_month_reports_no_change_partway_through(): void
    {
        CarbonImmutable::setTestNow('2026-07-18');

        $user = User::factory()->create();
        $this->spendDaily($user, '2026-06-01', '2026-06-30', 10.00);
        $this->spendDaily($user, '2026-07-01', '2026-07-18', 10.00);

        $response = $this->actingAs($user)->get('/reports?period=month&at=2026-07');

        $response->assertOk();

        $stats = $response->viewData('page')['props']['stats'];

        // 18 days at $10 against the first 18 days of June, not all 30 of them.
        $this->assertSame(180.0, $stats['total']);
        $this->assertSame(180.0, $stats['previous']);
        $this->assertSame(0.0, $stats['change_percent']);
        $this->assertTrue($stats['previous_is_partial']);
    }

    /** A real increase still shows as one — the guard must not flatten everything. */
    public function test_a_genuine_rise_is_still_reported(): void
    {
        CarbonImmutable::setTestNow('2026-07-18');

        $user = User::factory()->create();
        $this->spendDaily($user, '2026-06-01', '2026-06-30', 10.00);
        $this->spendDaily($user, '2026-07-01', '2026-07-18', 20.00);

        $stats = $this->actingAs($user)
            ->get('/reports?period=month&at=2026-07')
            ->viewData('page')['props']['stats'];

        $this->assertSame(360.0, $stats['total']);
        $this->assertSame(180.0, $stats['previous']);
        $this->assertSame(100.0, $stats['change_percent']);
    }

    /**
     * On a finished period there is nothing to truncate, so the whole of the
     * previous one counts and the flag says so.
     */
    public function test_a_completed_month_compares_against_the_whole_previous_one(): void
    {
        CarbonImmutable::setTestNow('2026-08-15');

        $user = User::factory()->create();
        $this->spendDaily($user, '2026-06-01', '2026-06-30', 10.00);
        $this->spendDaily($user, '2026-07-01', '2026-07-31', 10.00);

        $stats = $this->actingAs($user)
            ->get('/reports?period=month&at=2026-07')
            ->viewData('page')['props']['stats'];

        $this->assertSame(310.0, $stats['total']);
        $this->assertSame(300.0, $stats['previous']);
        $this->assertFalse($stats['previous_is_partial']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
