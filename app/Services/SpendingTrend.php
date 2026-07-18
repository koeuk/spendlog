<?php

namespace App\Services;

use App\Enums\TrendGranularity;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Spend over time for one period, plus the list of periods worth offering.
 *
 * One series per request rather than all three at once: the chart can now look
 * back over any month or year, so the combinations are unbounded and the old
 * ship-everything approach would have to guess which slice you wanted.
 */
class SpendingTrend
{
    /** How many past periods the picker offers, at most. */
    private const MAX_OPTIONS = [
        TrendGranularity::Week->value => 12,
        TrendGranularity::Month->value => 24,
        TrendGranularity::Year->value => 10,
        // All time is one span; the picker has nothing to choose between.
        TrendGranularity::All->value => 1,
    ];

    /**
     * @return array{label: string, total: float, buckets: array}
     */
    public function series(User $user, TrendGranularity $granularity, CarbonImmutable $anchor): array
    {
        return match ($granularity) {
            TrendGranularity::Week => $this->week($user, $anchor),
            TrendGranularity::Month => $this->month($user, $anchor),
            TrendGranularity::Year => $this->year($user, $anchor),
            TrendGranularity::All => $this->all($user),
        };
    }

    /**
     * The periods the dropdown lists — bounded by the user's own history, so a
     * new account is not offered ten empty years to browse.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(User $user, TrendGranularity $granularity, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $earliest = $this->earliestExpense($user) ?? $now;
        $limit = self::MAX_OPTIONS[$granularity->value];

        if ($granularity === TrendGranularity::All) {
            return [['value' => 'all', 'label' => $this->periodLabel($granularity, $earliest)]];
        }

        $options = [];
        $cursor = $this->startOf($granularity, $now);
        $floor = $this->startOf($granularity, $earliest);

        while ($cursor->gte($floor) && count($options) < $limit) {
            $options[] = [
                'value' => $this->anchorValue($granularity, $cursor),
                'label' => $this->periodLabel($granularity, $cursor),
            ];

            $cursor = match ($granularity) {
                TrendGranularity::Week => $cursor->subWeek(),
                TrendGranularity::Month => $cursor->subMonth(),
                TrendGranularity::Year => $cursor->subYear(),
            };
        }

        return $options;
    }

    /**
     * Turn the picker's value back into a date, falling back to today when it is
     * missing or malformed — a bad query string should not 500 the dashboard.
     *
     * Typed mixed for the same reason as CalendarOptions::resolveMonth: callers
     * pass $request->query('at') through unfiltered, and '?at[]=1' makes that an
     * array, which a ?string signature rejected with a TypeError before any of
     * the fallback logic ran.
     */
    public function resolveAnchor(TrendGranularity $granularity, mixed $value, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        // All time has no anchor to resolve — the span is the whole history.
        if ($granularity === TrendGranularity::All || ! is_string($value) || $value === '') {
            return $now;
        }

        try {
            $date = match ($granularity) {
                TrendGranularity::Week => CarbonImmutable::createFromFormat('Y-m-d', $value),
                TrendGranularity::Month => CarbonImmutable::createFromFormat('Y-m-d', $value.'-01'),
                TrendGranularity::Year => CarbonImmutable::createFromFormat('Y-m-d', $value.'-01-01'),
            };
        } catch (\Throwable) {
            return $now;
        }

        if (! $date) {
            return $now;
        }

        /*
         * Carbon does not throw on an overflow, it rolls forward: '2025-13'
         * parses to January 2026 and would have been reported under that
         * heading, a plausible-looking answer to a question nobody asked.
         * resolveMonth guards this by formatting back; so does this now.
         */
        $canonical = match ($granularity) {
            TrendGranularity::Week => $date->format('Y-m-d'),
            TrendGranularity::Month => $date->format('Y-m'),
            TrendGranularity::Year => $date->format('Y'),
        };

        if ($canonical !== $value) {
            return $now;
        }

        // Never anchor to the future: there is nothing logged there to show.
        return $date->lte($now) ? $date : $now;
    }

    /**
     * The calendar span a granularity + anchor covers. Shared so the report's
     * totals and its chart can never disagree about where a period starts.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(TrendGranularity $granularity, CarbonImmutable $anchor, ?User $user = null): array
    {
        if ($granularity === TrendGranularity::All) {
            // Everything ever logged. Without a user we cannot know when that
            // starts, so fall back to a span wide enough to hold any history.
            $start = $user ? ($this->earliestExpense($user) ?? $anchor) : $anchor->subYears(50);

            return [$start->startOfDay(), CarbonImmutable::now()->endOfDay()];
        }

        return match ($granularity) {
            TrendGranularity::Week => [$anchor->startOfWeek(), $anchor->endOfWeek()],
            TrendGranularity::Month => [$anchor->startOfMonth(), $anchor->endOfMonth()],
            TrendGranularity::Year => [$anchor->startOfYear(), $anchor->endOfYear()],
        };
    }

    public function anchorValue(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->toDateString(),
            TrendGranularity::Month => $date->format('Y-m'),
            TrendGranularity::Year => $date->format('Y'),
            TrendGranularity::All => 'all',
        };
    }

    private function startOf(TrendGranularity $granularity, CarbonImmutable $date): CarbonImmutable
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek(),
            TrendGranularity::Month => $date->startOfMonth(),
            TrendGranularity::Year => $date->startOfYear(),
            TrendGranularity::All => $date->startOfMonth(),
        };
    }

    private function periodLabel(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->isoFormat('D MMM').' – '.$date->endOfWeek()->isoFormat('D MMM YYYY'),
            TrendGranularity::Month => $date->isoFormat('MMMM YYYY'),
            TrendGranularity::Year => $date->isoFormat('YYYY'),
            TrendGranularity::All => __('All time'),
        };
    }

    /**
     * Everything ever logged — one bar per month from the first expense to now.
     *
     * Months, not years: a two-year history would be two bars, which is not a
     * trend. Months keep the shape readable at any history length.
     */
    private function all(User $user): array
    {
        $earliest = $this->earliestExpense($user);
        $now = CarbonImmutable::now();

        if (! $earliest) {
            return $this->assemble(__('All time'), []);
        }

        $start = $earliest->startOfMonth();

        $totals = Expense::query()
            ->where('user_id', $user->id)
            // Grouped by the same expression that is selected: under
            // only_full_group_by, grouping by YEAR()/MONTH() while selecting
            // DATE_FORMAT() is rejected — MySQL cannot prove they agree.
            ->groupByRaw("DATE_FORMAT(spent_on, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(spent_on, '%Y-%m') as bucket, SUM(price) as total")
            ->pluck('total', 'bucket');

        $buckets = [];
        $span = $start->diffInMonths($now);

        for ($month = $start; $month->lte($now->endOfMonth()); $month = $month->addMonth()) {
            $buckets[] = $this->bucket(
                key: $month->format('Y-m'),
                // Once past a couple of years the month labels collide, so only
                // each January is named — the year is the useful landmark then.
                label: $span > 24
                    ? ($month->month === 1 ? $month->isoFormat('YYYY') : '')
                    : ($month->month === 1 ? $month->isoFormat('MMM YY') : $month->isoFormat('MMM')),
                caption: $month->isoFormat('MMMM YYYY'),
                value: (float) ($totals[$month->format('Y-m')] ?? 0),
                date: $month,
                granularity: TrendGranularity::Year,
            );
        }

        return $this->assemble(__('All time'), $buckets);
    }

    /** One bar per day, Monday to Sunday. */
    private function week(User $user, CarbonImmutable $anchor): array
    {
        $start = $anchor->startOfWeek();
        $end = $start->endOfWeek();
        $totals = $this->dailyTotals($user, $start, $end);

        $buckets = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $buckets[] = $this->bucket(
                key: $day->toDateString(),
                // Single letter: seven of these must fit a narrow card.
                label: $day->isoFormat('dd'),
                caption: $day->isoFormat('ddd D MMM'),
                value: (float) ($totals[$day->toDateString()] ?? 0),
                date: $day,
            );
        }

        return $this->assemble($this->periodLabel(TrendGranularity::Week, $start), $buckets);
    }

    /** One bar per day of the month. */
    private function month(User $user, CarbonImmutable $anchor): array
    {
        $start = $anchor->startOfMonth();
        $end = $anchor->endOfMonth();
        $totals = $this->dailyTotals($user, $start, $end);

        $buckets = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $buckets[] = $this->bucket(
                key: $day->toDateString(),
                // Only every 5th day is labelled; 31 labels would collide.
                label: ($day->day === 1 || $day->day % 5 === 0) ? (string) $day->day : '',
                caption: $day->isoFormat('ddd D MMM'),
                value: (float) ($totals[$day->toDateString()] ?? 0),
                date: $day,
            );
        }

        return $this->assemble($this->periodLabel(TrendGranularity::Month, $start), $buckets);
    }

    /** One bar per month of the year. */
    private function year(User $user, CarbonImmutable $anchor): array
    {
        $start = $anchor->startOfYear();
        $end = $anchor->endOfYear();

        $totals = Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw('MONTH(spent_on)')
            ->selectRaw('MONTH(spent_on) as bucket, SUM(price) as total')
            ->pluck('total', 'bucket');

        $buckets = [];

        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $buckets[] = $this->bucket(
                key: $month->format('Y-m'),
                label: $month->isoFormat('MMM'),
                caption: $month->isoFormat('MMMM YYYY'),
                value: (float) ($totals[$month->month] ?? 0),
                date: $month,
                granularity: TrendGranularity::Year,
            );
        }

        return $this->assemble($this->periodLabel(TrendGranularity::Year, $start), $buckets);
    }

    /**
     * @param  array<int, array>  $buckets
     */
    private function assemble(string $label, array $buckets): array
    {
        return [
            'label' => $label,
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

    private function earliestExpense(User $user): ?CarbonImmutable
    {
        $earliest = Expense::query()->where('user_id', $user->id)->min('spent_on');

        return $earliest ? CarbonImmutable::parse($earliest) : null;
    }

    private function bucket(
        string $key,
        string $label,
        string $caption,
        float $value,
        CarbonImmutable $date,
        TrendGranularity $granularity = TrendGranularity::Month,
    ): array {
        $now = CarbonImmutable::now();

        $isCurrent = $granularity === TrendGranularity::Year
            ? $date->isSameMonth($now)
            : $date->isSameDay($now);

        // Buckets that have not happened yet are drawn as empty, not as a zero —
        // "nothing spent" and "not yet" are different claims.
        $isFuture = $granularity === TrendGranularity::Year
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
