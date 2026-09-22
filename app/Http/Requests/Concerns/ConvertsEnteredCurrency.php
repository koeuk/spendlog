<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Currency;
use App\Models\AppSetting;

/**
 * Every stored amount is USD — see App\Enums\Currency.
 *
 * The currency is a property of what was *typed*, not of the record: someone
 * pricing in riel still owes the same dollars. So it is consumed on the way in
 * and never persisted, and every money request needed the same two lines to do
 * it. Eight of them did, which is eight places for the rate lookup to be
 * forgotten — and an unconverted riel amount reads as ~4100x its real size, so
 * a budget or a savings plan silently never reports as met.
 */
trait ConvertsEnteredCurrency
{
    /** What the amount was entered in. Absent means USD, so older clients keep working. */
    protected function enteredCurrency(): Currency
    {
        return Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
    }

    /** A validated amount field, in the USD that is actually stored. */
    public function usdAmount(string $key = 'amount'): float
    {
        return $this->enteredCurrency()->toUsd(
            (float) $this->validated($key),
            AppSetting::current()->khrPerUsd(),
        );
    }
}
