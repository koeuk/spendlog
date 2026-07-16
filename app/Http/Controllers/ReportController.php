<?php

namespace App\Http\Controllers;

use App\Enums\TrendGranularity;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\SpendingTrend;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard answers "how am I doing right now"; this page answers "where did
 * the money actually go over a period" — the same numbers, but broken down and
 * comparable against the period before.
 */
class ReportController extends Controller
{
    public function __construct(private readonly SpendingTrend $trend) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // A junk ?period= falls back rather than 500s — it is a query string.
        $granularity = TrendGranularity::tryFrom((string) $request->query('period'))
            ?? TrendGranularity::Month;

        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));
        [$start, $end] = $this->trend->range($granularity, $anchor);

        $series = $this->trend->series($user, $granularity, $anchor);
        $breakdown = $this->breakdown($user, $start, $end);

        return Inertia::render('Reports/Index', [
            'granularity' => $granularity->value,
            'anchor' => $this->trend->anchorValue($granularity, $anchor),
            'options' => $this->trend->options($user, $granularity),
            'series' => $series,
            'breakdown' => $breakdown,
            'stats' => $this->stats($user, $start, $end, $granularity, $anchor, $breakdown),
        ]);
    }

    /**
     * Spend per category over the range, largest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(price) as total, COUNT(*) as count')
            ->get()
            ->keyBy('category_id');

        $total = (float) $rows->sum('total');

        return Category::query()
            ->whereIn('id', $rows->keys())
            ->get()
            ->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                'name' => $category->name,
                'color' => $category->color?->value,
                'icon' => $category->icon?->value,
                'total' => round((float) $rows[$category->id]->total, 2),
                'count' => (int) $rows[$category->id]->count,
                'average' => round((float) $rows[$category->id]->total / max($rows[$category->id]->count, 1), 2),
                'share' => $total > 0 ? round(((float) $rows[$category->id]->total / $total) * 100, 1) : 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Headline figures, including the change against the previous period —
     * a total means little without something to compare it to.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function stats(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        TrendGranularity $granularity,
        CarbonImmutable $anchor,
        array $breakdown,
    ): array {
        $total = round(array_sum(array_column($breakdown, 'total')), 2);
        $count = array_sum(array_column($breakdown, 'count'));

        // Averaged over elapsed days only: dividing this month's spend by 31 on
        // the 3rd would report a daily average three times lower than reality.
        $now = CarbonImmutable::now();
        $last = $end->gt($now) ? $now : $end;
        $days = max($start->diffInDays($last) + 1, 1);

        $previousAnchor = match ($granularity) {
            TrendGranularity::Week => $anchor->subWeek(),
            TrendGranularity::Month => $anchor->subMonth(),
            TrendGranularity::Year => $anchor->subYear(),
        };

        [$prevStart, $prevEnd] = $this->trend->range($granularity, $previousAnchor);

        $previous = round((float) Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('price'), 2);

        return [
            'total' => $total,
            'count' => $count,
            'daily_average' => round($total / $days, 2),
            'previous' => $previous,
            // Null, not 0: with nothing to compare against, "+100%" would be a
            // fabricated claim rather than a measurement.
            'change_percent' => $previous > 0
                ? round((($total - $previous) / $previous) * 100, 1)
                : null,
            'previous_label' => $this->trend->options($user, $granularity)
                ? $this->periodLabelFor($granularity, $previousAnchor)
                : null,
        ];
    }

    private function periodLabelFor(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->isoFormat('D MMM').' – '.$date->endOfWeek()->isoFormat('D MMM YYYY'),
            TrendGranularity::Month => $date->isoFormat('MMMM YYYY'),
            TrendGranularity::Year => $date->isoFormat('YYYY'),
        };
    }
}
