<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Enums\TrendGranularity;
use App\Services\SpendingTrend;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** How many expenses the "recent" list shows. */
    private const RECENT_LIMIT = 8;

    public function __construct(
        private readonly BudgetSummary $summary,
        private readonly SpendingTrend $trend,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewDashboard');

        $user = $request->user();
        $today = CarbonImmutable::now();

        // The month totals and every budget figure come from the same service the
        // Budgets page uses, so the two screens can never disagree.
        $summary = $this->summary->forMonth($user, $today->startOfMonth());

        return Inertia::render('Dashboard', [
            'today' => [
                'date' => $today->toDateString(),
                'total' => $this->todayTotal($user, $today),
            ],
            'summary' => $summary,
            // Its own period, independent of the budget figures above: a budget
            // is a monthly amount, but "where it went" is just spend, so it can
            // be asked over any span.
            'breakdown' => $this->breakdown($request),
            'breakdown_period' => $this->breakdownPeriod($request)->value,
            'trend' => $this->trendPayload($request),
            'recent' => $this->recent($user),
            // Admin-authored, already resolved to the active locale. Null when the
            // feature is off or unwritten, and the card then renders nothing.
            'guidance' => AppSetting::current()->spendingGuidance(),
        ]);
    }

    /**
     * The chart's own slice of state. Everything it needs to render and to
     * repopulate its own controls travels together, so the page can reload just
     * this key when the period changes.
     *
     * @return array<string, mixed>
     */
    private function trendPayload(Request $request): array
    {
        $user = $request->user();

        // A junk ?trend= falls back rather than 500s — it is a query string, not a form.
        $granularity = TrendGranularity::tryFrom((string) $request->query('trend'))
            ?? TrendGranularity::Month;

        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));

        return [
            'granularity' => $granularity->value,
            'anchor' => $this->trend->anchorValue($granularity, $anchor),
            'options' => $this->trend->options($user, $granularity),
            'series' => $this->trend->series($user, $granularity, $anchor),
        ];
    }

    private function todayTotal(User $user, CarbonImmutable $today): float
    {
        return round((float) Expense::query()
            ->where('user_id', $user->id)
            ->whereDate('spent_on', $today->toDateString())
            ->sum('price'), 2);
    }

    /**
     * The period the breakdown card is showing.
     *
     * A junk ?breakdown= falls back to the month rather than 500ing — it is a
     * query string, not a form, and the month is what the card showed before it
     * had a picker at all.
     */
    private function breakdownPeriod(Request $request): TrendGranularity
    {
        return TrendGranularity::tryFrom((string) $request->query('breakdown'))
            ?? TrendGranularity::Month;
    }

    /**
     * Categories that actually saw spend in the chosen period, largest first,
     * each with its share of that period's total — the share is what the bars
     * encode.
     *
     * Queried directly rather than read off the budget summary: that summary is
     * anchored to a month by design (budgets are monthly), so deriving a weekly
     * or yearly breakdown from it would silently keep reporting the month.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(Request $request): array
    {
        [$start, $end] = $this->breakdownPeriod($request)->range(CarbonImmutable::now());

        $spend = Expense::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(price) as total')
            ->pluck('total', 'category_id');

        $total = (float) $spend->sum();

        if ($total <= 0) {
            return [];
        }

        // Only the categories that appear — an empty row would draw a zero-width
        // bar and take a line for nothing.
        return Category::query()
            ->whereIn('id', $spend->keys())
            ->get(['id', 'uuid', 'name', 'color', 'icon'])
            ->map(function (Category $category) use ($spend, $total) {
                $spent = (float) $spend[$category->id];

                return [
                    'uuid' => $category->uuid,
                    // Read through the accessor, so the active locale resolves —
                    // toArray() would hand the raw {"en":…,"km":…} to the card.
                    'name' => $category->name,
                    'color' => $category->color?->value,
                    'icon' => $category->icon?->value,
                    'spent' => round($spent, 2),
                    'share' => round(($spent / $total) * 100, 1),
                ];
            })
            ->sortByDesc('spent')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recent(User $user): array
    {
        return Expense::query()
            ->with('category:id,uuid,name,color,icon')
            ->where('user_id', $user->id)
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Expense $expense) => [
                'uuid' => $expense->uuid,
                'item' => $expense->item,
                'price' => (float) $expense->price,
                'spent_on' => $expense->spent_on->toDateString(),
                'category' => $expense->category->name,
                'color' => $expense->category->color?->value,
                'icon' => $expense->category->icon?->value,
            ])
            ->all();
    }
}
