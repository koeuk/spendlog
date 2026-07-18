<?php

namespace App\Enums;

/**
 * The currencies a price can be *entered* in.
 *
 * Deliberately not the currency an expense is *stored* in: every amount in the
 * database is USD. Budgets, the over-budget banner, the dashboard totals and the
 * reports all SUM(price), and a column holding two currencies makes every one of
 * those sums meaningless. So KHR is an input convenience — converted on the way
 * in, at the rate on AppSetting — and there is exactly one currency downstream.
 */
enum Currency: string
{
    case Usd = 'USD';
    case Khr = 'KHR';

    /** The symbol shown beside an amount in this currency. */
    public function symbol(): string
    {
        return match ($this) {
            self::Usd => '$',
            self::Khr => '៛',
        };
    }

    /**
     * Convert an entered amount into the USD value that gets stored.
     *
     * Rounded to cents because that is what the column holds — leaving the extra
     * precision to the decimal cast would truncate rather than round, so ៛10,000
     * at 4100 would store 2.43 instead of 2.44.
     */
    public function toUsd(float $amount, float $khrPerUsd): float
    {
        return match ($this) {
            self::Usd => round($amount, 2),
            self::Khr => round($amount / $khrPerUsd, 2),
        };
    }
}
