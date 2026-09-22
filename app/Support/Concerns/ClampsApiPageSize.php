<?php

namespace App\Support\Concerns;

use Illuminate\Http\Request;

/**
 * The API's ?per_page contract: a default, and a ceiling.
 *
 * Deliberately not PaginatesLists, which the web lists use. That trait offers
 * an allow-list because its page size comes from a picker with fixed options;
 * an API client has no picker and asks for what suits its screen, so anything
 * up to the ceiling is honoured. The ceiling is the point — per_page goes
 * straight into a LIMIT, and ?per_page=100000 is a cheap way to make the
 * server build a hundred thousand rows.
 */
trait ClampsApiPageSize
{
    /** Matches the web list; ?per_page can narrow it for a phone screen. */
    protected const PER_PAGE = 50;

    protected const MAX_PER_PAGE = 100;

    protected function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', static::PER_PAGE);

        return max(1, min($requested, static::MAX_PER_PAGE));
    }
}
