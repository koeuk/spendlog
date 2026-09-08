<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How often a recurring rule fires, and the arithmetic for stepping from one
 * occurrence to the next.
 */
enum RecurringFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * The occurrence after $from.
     *
     * Monthly and yearly keep the day the rule started on, clamped to the last
     * day of a shorter month — and clamped per step, from the original day,
     * not from the previous occurrence. Stepping Jan 31 with Carbon's own
     * addMonth() overflows into March, and addMonthNoOverflow() lands on Feb
     * 28 and then stays on the 28th for good; a rule for "the 31st" has to
     * come back to Mar 31, which is why the anchor day travels separately.
     */
    public function next(CarbonImmutable $from, int $anchorDay): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $from->addDay(),
            self::Weekly => $from->addWeek(),
            self::Monthly => self::clampDay($from->startOfMonth()->addMonth(), $anchorDay),
            self::Yearly => self::clampDay($from->startOfMonth()->addYear(), $anchorDay),
        };
    }

    private static function clampDay(CarbonImmutable $monthStart, int $anchorDay): CarbonImmutable
    {
        return $monthStart->setDay(min($anchorDay, $monthStart->daysInMonth));
    }
}
