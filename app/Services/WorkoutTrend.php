<?php

namespace App\Services;

use App\Enums\TrendGranularity;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Training volume over time for one period, plus the periods worth offering.
 *
 * Deliberately the same public shape as SpendingTrend — series(), options(),
 * resolveAnchor(), range() — and, more importantly, the same *bucket* shape:
 * {key, label, caption, value, is_current, is_future}. That is what lets the
 * exercise dashboard reuse SpendingTrendChart.vue unchanged instead of shipping
 * a second hand-rolled SVG chart. If you change a bucket key here, that chart
 * stops reading this series.
 *
 * The value is volume in kilograms (Σ reps × weight). Cardio contributes
 * nothing — it has no weight — so a running-only week reads as a flat line.
 * That is why the dashboard shows sessions and minutes as separate figures
 * rather than trying to fold them into this one number.
 */
class WorkoutTrend
{
    /** How many past periods the picker offers, at most. Mirrors SpendingTrend. */
    private const MAX_OPTIONS = [
        TrendGranularity::Week->value => 12,
        TrendGranularity::Month->value => 24,
        TrendGranularity::Year->value => 10,
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
     * @return array<int, array{value: string, label: string}>
     */
    public function options(User $user, TrendGranularity $granularity, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $earliest = $this->earliestWorkout($user) ?? $now;
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
     */
    public function resolveAnchor(TrendGranularity $granularity, ?string $value, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        if ($granularity === TrendGranularity::All || ! $value) {
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

    /** One bar per month from the first workout to now. */
    private function all(User $user): array
    {
        $earliest = $this->earliestWorkout($user);
        $now = CarbonImmutable::now();

        if (! $earliest) {
            return $this->assemble(__('All time'), []);
        }

        $start = $earliest->startOfMonth();

        $totals = $this->volumeQuery($user)
            // Grouped by the same expression that is selected: under
            // only_full_group_by, grouping by YEAR()/MONTH() while selecting
            // DATE_FORMAT() is rejected — MySQL cannot prove they agree.
            ->groupByRaw("DATE_FORMAT(workouts.performed_on, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(workouts.performed_on, '%Y-%m') as bucket, SUM(workout_sets.reps * workout_sets.weight_kg) as total")
            ->pluck('total', 'bucket');

        $buckets = [];
        $span = $start->diffInMonths($now);

        for ($month = $start; $month->lte($now->endOfMonth()); $month = $month->addMonth()) {
            $buckets[] = $this->bucket(
                key: $month->format('Y-m'),
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
        $totals = $this->dailyVolume($user, $start, $end);

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
        $totals = $this->dailyVolume($user, $start, $end);

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

        $totals = $this->volumeQuery($user)
            ->whereBetween('workouts.performed_on', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw('MONTH(workouts.performed_on)')
            ->selectRaw('MONTH(workouts.performed_on) as bucket, SUM(workout_sets.reps * workout_sets.weight_kg) as total')
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
            // Whole kilograms: volume runs to five figures, so decimals here are
            // noise, unlike money where the cents are the point.
            'total' => round(array_sum(array_column($buckets, 'value'))),
            'buckets' => $buckets,
        ];
    }

    /**
     * Volume keyed by 'Y-m-d'.
     *
     * @return Collection<string, string>
     */
    private function dailyVolume(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $this->volumeQuery($user)
            ->whereBetween('workouts.performed_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('workouts.performed_on')
            ->selectRaw('workouts.performed_on as bucket, SUM(workout_sets.reps * workout_sets.weight_kg) as total')
            ->pluck('total', 'bucket')
            // MySQL hands back a date, not the string the loop keys on.
            ->mapWithKeys(fn ($total, $bucket) => [CarbonImmutable::parse($bucket)->toDateString() => $total]);
    }

    /**
     * The join every volume figure is built on.
     *
     * Both null checks matter: SUM over a NULL weight yields NULL rather than 0,
     * so a month of nothing but cardio would otherwise come back as null and
     * cast to a misleading 0.0 only by luck of PHP's coercion.
     */
    private function volumeQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereNotNull('workout_sets.reps')
            ->whereNotNull('workout_sets.weight_kg');
    }

    private function earliestWorkout(User $user): ?CarbonImmutable
    {
        $earliest = Workout::query()->where('user_id', $user->id)->min('performed_on');

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
        // "did not train" and "not yet" are different claims.
        $isFuture = $granularity === TrendGranularity::Year
            ? $date->startOfMonth()->gt($now->startOfMonth())
            : $date->gt($now);

        return [
            'key' => $key,
            'label' => $label,
            'caption' => $caption,
            'value' => round($value),
            'is_current' => $isCurrent,
            'is_future' => $isFuture,
        ];
    }
}
