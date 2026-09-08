<?php

namespace App\Support\Concerns;

/**
 * The month and year query strings every dated list accepts.
 *
 * Two independent controls: a year alone shows that whole year, a month alone
 * shows that month in every year, and together they pin one month. Junk is
 * ignored rather than 500ing — these are query strings, not a form.
 */
trait ValidatesDateFilters
{
    /** '01'–'12', or '' for "every month". */
    protected function validMonth(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^(0[1-9]|1[0-2])$/', $value) === 1 ? $value : '';
    }

    /** A four-digit year, or '' for "every year". */
    protected function validYear(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}$/', $value) === 1 ? $value : '';
    }
}
