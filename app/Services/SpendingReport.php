<?php

namespace App\Services;

use App\Enums\TrendGranularity;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The report's figures: spend per category over a period, and the headline
 * totals including the change against the period before.
 *
 * Shared rather than private to a controller, for the same reason BudgetSummary
 * and CategoryBreakdown are: the web Reports page, its PDF export and the API
 * all answer the same question, and a copy of this arithmetic in each of them
 * is a drift waiting to happen.
 *
 * SpendingTrend owns *when* a period starts and ends; this owns *what* was
 * spent inside it.
 */
class SpendingReport
{
    public function __construct(private readonly SpendingTrend $trend) {}

    /**
     * Spend per category over the range, largest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function breakdown(User $user, CarbonImmutable $start, CarbonImmutable $end): array
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
    public function stats(
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

        // Count whole calendar days. $end carries a 23:59:59.999999 time, so the
        // raw float diff already spans the final day — adding one to it would
        // bill a day that never elapsed and report the average ~12% low.
        $days = max($start->startOfDay()->diffInDays($last->startOfDay()) + 1, 1);

        // All time has nothing before it, so there is no comparison to make.
        // Each anchor is normalised before stepping back: subMonth() from the
        // 31st overflows forward into the current month, which would compare the
        // period being reported against itself.
        $previousAnchor = match ($granularity) {
            TrendGranularity::Week => $anchor->startOfWeek()->subWeek(),
            TrendGranularity::Month => $anchor->startOfMonth()->subMonth(),
            TrendGranularity::Year => $anchor->startOfYear()->subYear(),
            TrendGranularity::All => null,
        };

        $previous = 0.0;
        $partial = false;

        if ($previousAnchor !== null) {
            [$prevStart, $prevEnd] = $this->trend->range($granularity, $previousAnchor, $user);

            /*
             * Truncated to the days that have actually elapsed this period, for
             * the same reason daily_average is: on the 18th, $total holds 18
             * days and the previous month holds 30. Comparing them reported a
             * 40% fall for someone whose spending had not changed at all — and
             * it read correctly only on the last day of a period.
             *
             * $days already counts the elapsed span, so the window is the first
             * $days of the previous period. Clamped to $prevEnd because a longer
             * month compared against a shorter one would otherwise reach past it.
             */
            $prevCutoff = $prevStart->startOfDay()->addDays($days - 1);

            if ($prevCutoff->gt($prevEnd)) {
                $prevCutoff = $prevEnd;
            }

            $partial = $prevCutoff->lt($prevEnd->startOfDay());

            $previous = round((float) Expense::query()
                ->where('user_id', $user->id)
                ->whereBetween('spent_on', [$prevStart->toDateString(), $prevCutoff->toDateString()])
                ->sum('price'), 2);
        }

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
            'previous_label' => $previousAnchor !== null
                ? $this->trend->periodLabel($granularity, $previousAnchor)
                : null,
            // True while the current period is still running, so the UI can say
            // the comparison is against the same stretch of the previous one
            // rather than the whole of it.
            'previous_is_partial' => $partial,
        ];
    }
}
