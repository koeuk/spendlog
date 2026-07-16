<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStatsTest extends TestCase
{
    use RefreshDatabase;

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

    private function spend(User $user, string $on, float $price): void
    {
        Expense::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::factory()->create()->id,
            'spent_on' => $on,
            'price' => $price,
        ]);
    }

    /**
     * A month anchored on the 31st has no 31st to step back to. Carbon's default
     * subMonth() overflows forward into the current month, which made the report
     * compare July against July and call it 0% change.
     */
    public function test_previous_period_is_the_previous_month_when_anchored_on_a_day_it_lacks(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 09:00:00');

        $user = $this->user();
        $this->spend($user, '2026-06-10', 100);
        $this->spend($user, '2026-07-05', 40);

        $response = $this->actingAs($user)->get('/reports?period=month');

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        // June's 100, not July's own 40 handed back to it.
        $this->assertSame(40.0, $stats['total']);
        $this->assertSame(100.0, $stats['previous']);
        $this->assertNotSame(0.0, $stats['change_percent']);
    }

    /**
     * Guards every month-end anchor, not just the one date that surfaced it.
     */
    public function test_month_end_anchors_never_compare_a_month_against_itself(): void
    {
        $user = $this->user();

        foreach (['2026-03-31', '2026-05-31', '2026-07-31', '2026-10-31', '2026-12-31'] as $date) {
            CarbonImmutable::setTestNow($date.' 09:00:00');

            $response = $this->actingAs($user)->get('/reports?period=month');
            $response->assertOk();

            $props = $response->viewData('page')['props'];

            $this->assertNotSame(
                $props['series']['label'],
                $props['stats']['previous_label'],
                "anchored on {$date}, the previous period is the reported period"
            );
        }
    }

    /**
     * $end sits at 23:59:59.999999, so Carbon 3's float diffInDays already spans
     * the final day. The old + 1 billed a day that never elapsed, reporting the
     * average 12.5% low on a week.
     */
    public function test_daily_average_divides_by_whole_elapsed_days(): void
    {
        // A fully elapsed week: 700 over 7 days is 100/day, not 87.50.
        CarbonImmutable::setTestNow('2026-07-20 09:00:00');

        $user = $this->user();

        foreach (['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10', '2026-07-11', '2026-07-12'] as $day) {
            $this->spend($user, $day, 100);
        }

        $response = $this->actingAs($user)->get('/reports?period=week&at=2026-07-06');

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(700.0, $stats['total']);
        $this->assertSame(100.0, $stats['daily_average']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
