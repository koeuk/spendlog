<?php

namespace App\Support\Concerns;

use Illuminate\Http\Request;

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

    /**
     * Where the list was when a form was opened, so saving returns to the same
     * month rather than to an unfiltered list.
     *
     * Whitelisted by key and revalidated, never echoed: this round-trips
     * through a form field, and handing user input to a redirect is how open
     * redirects happen. Only the keys named here survive, and each is checked
     * by the same helpers the list itself uses. A page with more state than a
     * date — a scope, say — adds it to what this returns.
     *
     * @return array<string, string>
     */
    protected function dateReturnQuery(Request $request): array
    {
        $source = $this->returnQuerySource($request);

        return array_filter([
            'month' => $this->validMonth($source['month'] ?? null),
            'year' => $this->validYear($source['year'] ?? null),
        ], fn (string $value) => $value !== '');
    }

    /**
     * GET create/edit carry the state in the query string; the save that
     * follows posts it back in the body.
     *
     * @return array<string, mixed>
     */
    protected function returnQuerySource(Request $request): array
    {
        return is_array($request->input('return_query'))
            ? $request->input('return_query')
            : $request->query();
    }
}
