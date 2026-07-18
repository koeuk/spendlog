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

    /** Decimal places the price/amount columns hold. */
    public const SCALE = 4;

    /**
     * Convert an entered amount into the USD value that gets stored.
     *
     * Rounded here rather than left to the decimal cast, which truncates: at two
     * places ៛10,000 at 4100 would store 2.43 instead of 2.44.
     *
     * Four places, not two, because one cent is worth ~41៛ — rounding a riel
     * amount to cents snaps it to a multiple of 41៛, so ៛100 became $0.02 and
     * read back as ៛82. At four places the floor is ~0.4៛, finer than the
     * smallest note in circulation.
     */
    public function toUsd(float $amount, float $khrPerUsd): float
    {
        return match ($this) {
            self::Usd => round($amount, self::SCALE),
            self::Khr => round($amount / $khrPerUsd, self::SCALE),
        };
    }
}
