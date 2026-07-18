<?php

namespace App\Http\Controllers;

use App\Enums\TrendGranularity;
use App\Models\AppSetting;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Services\CategoryBreakdown;
use App\Services\SpendingTrend;
use App\Support\CalendarOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** How many expenses the "recent" list shows. */
    private const RECENT_LIMIT = 8;

    public function __construct(
        private readonly BudgetSummary $summary,
        private readonly SpendingTrend $trend,
        private readonly CategoryBreakdown $breakdown,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewDashboard');

        $user = $request->user();
        $today = CarbonImmutable::now();

        /*
         * Each card below picks its own month, and none of them may move the
         * hero: that card is labelled "this month", so it is anchored to the
         * real one. Reading it off whichever month a card happened to be
         * filtered to would retitle nothing and silently show another month's
         * numbers under the same heading.
         */
        $currentMonth = $today->startOfMonth();
        $budgetMonth = CalendarOptions::resolveMonth($request->query('budget_month'));
        $breakdownMonth = CalendarOptions::resolveMonth($request->query('breakdown_month'));

        // The month totals and every budget figure come from the same service the
        // Budgets page uses, so the two screens can never disagree.
        $summary = $this->summary->forMonth($user, $currentMonth);

        return Inertia::render('Dashboard', [
            'today' => [
                'date' => $today->toDateString(),
                'total' => $this->todayTotal($user, $today),
            ],
            // The page heading, which stays on the real current month however
            // the cards below are filtered — retitling the whole dashboard to a
            // month whose spend it is not showing would be a lie.
            'current_month' => $today->format('Y-m'),
            'summary' => $summary,
            // The Budgets card's own rows, for its own month — kept apart from
            // `summary` so choosing a month here cannot move the hero.
            'budgets' => $this->summary->forMonth($user, $budgetMonth)['categories'],
            'budget_month' => $budgetMonth->format('Y-m'),
            // Names come from the server so they follow the app locale, and from
            // the same source as the Budgets page so the lists cannot drift.
            'budget_months' => CalendarOptions::months(),
            'budget_years' => CalendarOptions::years($user, $budgetMonth),
            // Its own month, independent of both the hero and the budget card.
            'breakdown' => $this->breakdown->forMonth($user, $breakdownMonth),
            'breakdown_month' => $breakdownMonth->format('Y-m'),
            'breakdown_months' => CalendarOptions::months(),
            'breakdown_years' => CalendarOptions::years($user, $breakdownMonth),
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
