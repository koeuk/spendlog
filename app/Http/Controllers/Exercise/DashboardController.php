<?php

namespace App\Http\Controllers\Exercise;

use App\Enums\TrendGranularity;
use App\Http\Controllers\Controller;
use App\Models\Workout;
use App\Services\MuscleGroupBreakdown;
use App\Services\WorkoutSummary;
use App\Services\WorkoutTrend;
use App\Support\CalendarOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WorkoutSummary $summary,
        private readonly MuscleGroupBreakdown $breakdown,
        private readonly WorkoutTrend $trend,
    ) {}

    public function index(Request $request): Response
    {
        // The module's front door. Everything under /exercise is gated on the
        // same policy, so a user without it cannot reach this by typing the URL.
        Gate::authorize('viewAny', Workout::class);

        $user = $request->user();

        /*
         * Two independent month controls, mirroring the spending dashboard:
         * ?month= drives the headline figures, ?breakdown_month= the muscle
         * split. Each resolves on its own so moving one does not drag the other.
         */
        $month = CalendarOptions::resolveMonth($request->query('month'));
        $breakdownMonth = CalendarOptions::resolveMonth($request->query('breakdown_month'));

        $granularity = TrendGranularity::tryFrom((string) $request->query('trend')) ?? TrendGranularity::Month;
        $anchor = $this->trend->resolveAnchor($granularity, $request->query('trend_anchor'));

        // Twelve weeks back is what fits the heatmap without it becoming a wall.
        $heatmapEnd = CarbonImmutable::now()->endOfWeek();
        $heatmapStart = $heatmapEnd->subWeeks(11)->startOfWeek();

        return Inertia::render('Exercise/Dashboard', [
            'summary' => $this->summary->forMonth($user, $month),
            'streak' => $this->summary->currentStreak($user),
            'records' => $this->summary->personalRecords($user),

            'breakdown' => $this->breakdown->forMonth($user, $breakdownMonth),

            'trend' => $this->trend->series($user, $granularity, $anchor),
            'trend_granularity' => $granularity->value,
            'trend_anchor' => $this->trend->anchorValue($granularity, $anchor),
            'trend_options' => $this->trend->options($user, $granularity),

            'heatmap' => [
                'from' => $heatmapStart->toDateString(),
                'to' => $heatmapEnd->toDateString(),
                'days' => $this->summary->trainedDays($user, $heatmapStart, $heatmapEnd),
            ],

            // Seed the pickers so they reflect what is active on reload.
            'month' => $month->format('Y-m'),
            'breakdown_month' => $breakdownMonth->format('Y-m'),
            // Month names come from the server so they follow the app locale —
            // toLocaleDateString in the browser would follow the OS instead.
            'months' => CalendarOptions::months(),
            'years' => $this->yearOptions($user, $month),

            'can' => [
                'create' => $request->user()->can('create', Workout::class),
            ],
        ]);
    }

    /**
     * The years worth offering, drawn from this person's own history.
     *
     * Deliberately not CalendarOptions::years(), which reads expenses and
     * budgets — an account that has trained but never logged an expense would
     * be offered only the current year, and one that has logged expenses since
     * 2024 would be offered years with no workout in them.
     *
     * @return array<int, int>
     */
    private function yearOptions($user, CarbonImmutable $viewing): array
    {
        $earliest = Workout::query()->where('user_id', $user->id)->min('performed_on');

        $first = $earliest ? (int) CarbonImmutable::parse($earliest)->year : CarbonImmutable::now()->year;
        $last = CarbonImmutable::now()->year;

        return range(
            min($first, $viewing->year),
            max($last, $viewing->year),
        );
    }
}
