<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum TrendGranularity: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case All = 'all';

    /** The label the toggle shows; translated in the frontend. */
    public function label(): string
    {
        return match ($this) {
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
            self::All => 'All',
        };
    }

    /**
     * The inclusive [start, end] this period covers, anchored on $now.
     *
     * Always "this" period — the current week, month or year — which is the
     * reading every caller wants: the dashboard breakdown and the expenses list
     * both mean "so far", not "the last 7 days".
     *
     * All is a span wide enough to hold any row rather than a null, so callers
     * can apply one date range unconditionally instead of branching.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(CarbonImmutable $now): array
    {
        return match ($this) {
            self::Week => [$now->startOfWeek(), $now->endOfWeek()],
            self::Month => [$now->startOfMonth(), $now->endOfMonth()],
            self::Year => [$now->startOfYear(), $now->endOfYear()],
            self::All => [$now->startOfCentury(), $now->endOfCentury()],
        };
    }
}
