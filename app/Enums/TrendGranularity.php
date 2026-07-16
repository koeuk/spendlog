<?php

namespace App\Enums;

enum TrendGranularity: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** The label the toggle shows; translated in the frontend. */
    public function label(): string
    {
        return match ($this) {
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
        };
    }
}
