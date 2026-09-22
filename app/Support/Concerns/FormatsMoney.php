<?php

namespace App\Support\Concerns;

/**
 * Money is a string across the API, never a float.
 *
 * A JSON number cannot be trusted to survive the round trip: 12.50 serialises
 * as 12.5, and a client that renders what it is given then shows "$12.5". The
 * services deal in floats because they do arithmetic; the boundary formats.
 *
 * Every controller and resource that crossed that boundary used to carry its
 * own copy of this one line — seven of them, with five different signatures
 * and only one that handled null.
 */
trait FormatsMoney
{
    /**
     * Two decimal places, no thousands separator.
     *
     * Null passes through: "no budget set" is a different fact from "0.00",
     * and formatting it would erase the difference.
     */
    protected function money(int|float|string|null $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, '.', '');
    }
}
