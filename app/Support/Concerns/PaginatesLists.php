<?php

namespace App\Support\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * One page size and one payload shape for every list in the app.
 *
 * Without this each controller invents its own key names, and the shared
 * Pagination component then has to know three dialects of the same idea.
 */
trait PaginatesLists
{
    /**
     * The page sizes the picker offers.
     *
     * An allow-list, not a max: per_page goes straight into a LIMIT, and
     * ?per_page=100000 is a cheap way to make the server build a hundred
     * thousand rows. The first entry is the default.
     */
    public const PER_PAGE = [20, 50, 100, 150, 200];

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::PER_PAGE[0]);

        return in_array($perPage, self::PER_PAGE, true) ? $perPage : self::PER_PAGE[0];
    }

    /**
     * The pager's own state, separate from the rows themselves.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            // Null on an empty page; the component renders 0 rather than "null–null".
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'per_page' => $paginator->perPage(),
            'per_page_options' => self::PER_PAGE,
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }
}
