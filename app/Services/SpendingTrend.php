<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Spend over time, bucketed three ways for the dashboard chart.
 *
 * All three series ship together: the whole payload is ~50 numbers, far cheaper
 * than a round trip per toggle, so switching granularity is instant.
 */
class SpendingTrend
{
    /**
     * @return array{week: array, month: array, year: array}
     */
    public function forUser(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'week' => $this->week($user, $now),
            'month' => $this->month($user, $now),
            'year' => $this->year($user, $now),
        ];
    }

    /**
     * The current week, Monday to Sunday — one bar per day.
     *
     * @return array{label: string, total: float, buckets: array}
     */
    private function week(User $user, CarbonImmutable $now): array
    {
        $start = $now->startOfWeek();
        $totals = $this->dailyTotals($user, $start, $start->endOfWeek());

        $buckets = [];

        for ($day = $start; $day->lte($start->endOfWeek()); $day = $day->addDay()) {
            $buckets[] = $this->bucket(
                key: $day->toDateString(),
                // Single letter: seven of these must fit a narrow card.
                label: $day->isoFormat('dd'),
                caption: $day->isoFormat('ddd D MMM'),
                value: (float) ($totals[$day->toDateString()] ?? 0),
                now: $now,
                date: $day,
            );
        }

        return [
            'label' => $start->isoFormat('D MMM').' – '.$start->endOfWeek()->isoFormat('D MMM'),
            'total' => round(array_sum(array_column($buckets, 'value')), 2),
            'buckets' => $buckets,
        ];
    }

    /**
     * The current month — one bar per day.
     */
    private function month(User $user, CarbonImmutable $now): array
    {
        $start = $now->startOfMonth();
        $end = $now->endOfMonth();
        $totals = $this->dailyTotals($user, $start, $end);

        $buckets = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $buckets[] = $this->bucket(
                key: $day->toDateString(),
                // Only every 5th day is labelled; 31 labels would collide.
                label: ($day->day === 1 || $day->day % 5 === 0) ? (string) $day->day : '',
                caption: $day->isoFormat('ddd D MMM'),
                value: (float) ($totals[$day->toDateString()] ?? 0),
                now: $now,
                date: $day,
            );
        }

        return [
            'label' => $start->isoFormat('MMMM YYYY'),
            'total' => round(array_sum(array_column($buckets, 'value')), 2),
            'buckets' => $buckets,
        ];
    }

    /**
     * The current year — one bar per month.
     */
    private function year(User $user, CarbonImmutable $now): array
    {
        $start = $now->startOfYear();
        $end = $now->endOfYear();

        $totals = Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw('YEAR(spent_on), MONTH(spent_on)')
            ->selectRaw('MONTH(spent_on) as bucket, SUM(price) as total')
            ->pluck('total', 'bucket');

        $buckets = [];

        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $buckets[] = $this->bucket(
                key: $month->format('Y-m'),
                label: $month->isoFormat('MMM'),
                caption: $month->isoFormat('MMMM YYYY'),
                value: (float) ($totals[$month->month] ?? 0),
                now: $now,
                date: $month,
                granularity: 'month',
            );
        }

        return [
            'label' => $start->isoFormat('YYYY'),
            'total' => round(array_sum(array_column($buckets, 'value')), 2),
            'buckets' => $buckets,
        ];
    }

    /**
     * Totals keyed by 'Y-m-d'.
     *
     * @return Collection<string, string>
     */
    private function dailyTotals(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('spent_on')
            ->selectRaw('spent_on as bucket, SUM(price) as total')
            ->pluck('total', 'bucket')
            // MySQL hands back a date, not the string the loop keys on.
            ->mapWithKeys(fn ($total, $bucket) => [CarbonImmutable::parse($bucket)->toDateString() => $total]);
    }

    /**
     * @return array{key: string, label: string, caption: string, value: float, is_current: bool, is_future: bool}
     */
    private function bucket(
        string $key,
        string $label,
        string $caption,
        float $value,
        CarbonImmutable $now,
        CarbonImmutable $date,
        string $granularity = 'day',
    ): array {
        $isCurrent = $granularity === 'month'
            ? $date->isSameMonth($now)
            : $date->isSameDay($now);

        // Days that have not happened yet are drawn as empty track, not as a
        // zero — "nothing spent" and "not yet" are different claims.
        $isFuture = $granularity === 'month'
            ? $date->startOfMonth()->gt($now->startOfMonth())
            : $date->gt($now);

        return [
            'key' => $key,
            'label' => $label,
            'caption' => $caption,
            'value' => round($value, 2),
            'is_current' => $isCurrent,
            'is_future' => $isFuture,
        ];
    }
}
