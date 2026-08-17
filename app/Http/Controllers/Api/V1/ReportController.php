<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrendGranularity;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\User;
use App\Services\SpendingReport;
use App\Services\SpendingTrend;
use App\Support\Concerns\PaginatesLists;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Reports
 *
 * @authenticated
 */
class ReportController extends Controller
{
    use PaginatesLists;

    public function __construct(
        private readonly SpendingTrend $trend,
        private readonly SpendingReport $report,
    ) {}

    /**
     * Spending report
     *
     * Where the money actually went over a period, and how that compares to the
     * period before. The dashboard answers "how am I doing right now"; this
     * answers "what happened".
     *
     * Every figure comes from the same SpendingTrend / SpendingReport services
     * the web Reports page and its PDF export use, so the two clients cannot
     * disagree about the same period. Money is a string here, as everywhere in
     * this API; `share`, `percent` and `change_percent` are numbers.
     *
     * `change_percent` is `null` rather than `0` when there is nothing to
     * compare against — a fabricated "+100%" would read as a measurement.
     * While the current period is still running, `previous_is_partial` is true
     * and `previous` holds only the same elapsed stretch of the period before,
     * so a month in progress is not compared against a whole one.
     *
     * @response 200 {"data": {"granularity": "month", "anchor": "2026-08", "options": [{"value": "2026-08", "label": "August 2026"}], "series": {"label": "August 2026", "total": "648.34", "buckets": [{"key": "2026-08-01", "label": "1", "caption": "Sat 1 Aug", "value": "0.00", "is_current": false, "is_future": false}]}, "breakdown": [{"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils", "total": "23.00", "count": 4, "average": "5.75", "share": 3.5}], "stats": {"total": "648.34", "count": 8, "daily_average": "38.14", "previous": "0.00", "change_percent": null, "previous_label": "July 2026", "previous_is_partial": true}, "expenses": {"data": [], "meta": {"current_page": 1, "last_page": 1, "total": 0}, "links": {"next": null, "prev": null}}}}
     *
     * @queryParam period string One of `week`, `month`, `year`, `all`. Anything else falls back to `month`. Example: month
     * @queryParam at string The period to report on — `YYYY-MM-DD` for a week, `YYYY-MM` for a month, `YYYY` for a year. Ignored for `all`. Defaults to the current period. Example: 2026-08
     * @queryParam page integer Which page of the expense list. Example: 1
     * @queryParam per_page integer Rows per page — one of 20, 50, 100, 150, 200. Example: 20
     *
     * @response 403 scenario="token lacks reports:read" {"message": "Invalid ability provided."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewReports');

        $user = $request->user();

        // A junk ?period= falls back rather than 500s — it is a query string.
        $granularity = TrendGranularity::tryFrom((string) $request->query('period'))
            ?? TrendGranularity::Month;

        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));
        [$start, $end] = $this->trend->range($granularity, $anchor, $user);

        $breakdown = $this->report->breakdown($user, $start, $end);

        return response()->json([
            'data' => [
                'granularity' => $granularity->value,
                'anchor' => $this->trend->anchorValue($granularity, $anchor),
                'period_label' => $this->trend->periodLabel($granularity, $anchor),
                'options' => $this->trend->options($user, $granularity),
                'series' => $this->series($user, $granularity, $anchor),
                'breakdown' => $this->breakdown($breakdown),
                'stats' => $this->stats($user, $start, $end, $granularity, $anchor, $breakdown),
                'expenses' => $this->expenses($request, $user, $start, $end),
            ],
        ]);
    }

    /**
     * The chart, with every amount restated as a money string.
     *
     * @return array<string, mixed>
     */
    private function series(User $user, TrendGranularity $granularity, CarbonImmutable $anchor): array
    {
        $series = $this->trend->series($user, $granularity, $anchor);

        return [
            ...$series,
            'total' => $this->money($series['total']),
            'buckets' => array_map(
                fn (array $bucket) => [...$bucket, 'value' => $this->money($bucket['value'])],
                $series['buckets'],
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(array $breakdown): array
    {
        return array_map(fn (array $row) => [
            ...$row,
            'total' => $this->money($row['total']),
            'average' => $this->money($row['average']),
        ], $breakdown);
    }

    /**
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function stats(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        TrendGranularity $granularity,
        CarbonImmutable $anchor,
        array $breakdown,
    ): array {
        $stats = $this->report->stats($user, $start, $end, $granularity, $anchor, $breakdown);

        return [
            ...$stats,
            'total' => $this->money($stats['total']),
            'daily_average' => $this->money($stats['daily_average']),
            'previous' => $this->money($stats['previous']),
        ];
    }

    /**
     * The period's expenses, newest first.
     *
     * Paginated in the same envelope as `GET /expenses` — `data`, `meta`,
     * `links` — so a client can reuse one list component and one "load more"
     * for both screens.
     *
     * @return array<string, mixed>
     */
    private function expenses(Request $request, User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $paginator = Expense::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page')
            ->withQueryString();

        return [
            'data' => ExpenseResource::collection($paginator->items())->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }

    /**
     * Money is a string throughout this API — see the money note in docs/API.md.
     * The services deal in floats, so every amount is formatted on the way out
     * rather than leaking `12.5` for `12.50`.
     */
    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
