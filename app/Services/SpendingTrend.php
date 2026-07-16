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
     */
    public function resolveAnchor(TrendGranularity $granularity, ?string $value, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        if (! $value) {
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

        // Never anchor to the future: there is nothing logged there to show.
        return $date && $date->lte($now) ? $date : $now;
    }

    public function anchorValue(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->toDateString(),
            TrendGranularity::Month => $date->format('Y-m'),
            TrendGranularity::Year => $date->format('Y'),
        };
    }

    private function startOf(TrendGranularity $granularity, CarbonImmutable $date): CarbonImmutable
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek(),
            TrendGranularity::Month => $date->startOfMonth(),
            TrendGranularity::Year => $date->startOfYear(),
        };
    }

    private function periodLabel(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->isoFormat('D MMM').' – '.$date->endOfWeek()->isoFormat('D MMM YYYY'),
            TrendGranularity::Month => $date->isoFormat('MMMM YYYY'),
            TrendGranularity::Year => $date->isoFormat('YYYY'),
        };
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
