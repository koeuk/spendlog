<?php

namespace App\Http\Controllers;

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
            'breakdown' => $this->breakdown($summary),
            'trend' => $this->trendPayload($request),
            'recent' => $this->recent($user),
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
     * Categories that actually saw spend this month, largest first, each with
     * its share of the month's total — that share is what the bars encode.
     *
     * @param  array{overall: array, categories: array}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(array $summary): array
    {
        $total = (float) $summary['overall']['spent'];

        if ($total <= 0) {
            return [];
        }

        return collect($summary['categories'])
            ->filter(fn (array $category) => $category['spent'] > 0)
            ->sortByDesc('spent')
            ->map(fn (array $category) => [
                'uuid' => $category['uuid'],
                'name' => $category['name'],
                'color' => $category['color'],
                'icon' => $category['icon'],
                'spent' => $category['spent'],
                'share' => round(($category['spent'] / $total) * 100, 1),
            ])
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
