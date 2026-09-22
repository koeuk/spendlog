<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeRequest;
use App\Http\Resources\IncomeResource;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Support\CalendarOptions;
use App\Support\Concerns\ClampsApiPageSize;
use App\Support\Concerns\FormatsMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Income
 *
 * Money coming in. Every listing is scoped to the caller's own rows — there
 * is no "everyone" view; incomes.manage_all only lets an admin reach a single
 * row by UUID.
 *
 * @authenticated
 */
class IncomeController extends Controller
{
    use ClampsApiPageSize, FormatsMoney;

    /**
     * List income
     *
     * Paginated, newest first.
     *
     * @queryParam filter[source] string Partial match on the source. Example: salary
     * @queryParam filter[from] date Only income on or after this day. Example: 2026-09-01
     * @queryParam filter[to] date Only income on or before this day. Example: 2026-09-30
     * @queryParam sort string received_on, amount or source. Prefix with - to reverse. Example: -amount
     * @queryParam per_page int Default 50, clamped to 100. Example: 25
     *
     * @response 200 {"data": [{"uuid": "0198f...", "source": "Salary", "amount": "1200.00", "received_on": "2026-09-01", "note": null, "created_at": "2026-09-01T10:00:00+00:00", "updated_at": "2026-09-01T10:00:00+00:00"}], "links": {"first": "...", "last": "...", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 50, "total": 1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewAny', Income::class);

        $incomes = QueryBuilder::for(Income::class)
            ->allowedFilters(
                AllowedFilter::partial('source'),
                AllowedFilter::callback('from', fn ($query, $value) => $query->whereDate('received_on', '>=', $value)),
                AllowedFilter::callback('to', fn ($query, $value) => $query->whereDate('received_on', '<=', $value)),
            )
            ->allowedSorts('received_on', 'amount', 'source')
            ->defaultSort('-received_on', '-id')
            // Applied last so no filter can widen it beyond the owner's rows.
            ->where('user_id', $request->user()->id)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return IncomeResource::collection($incomes);
    }

    /**
     * Income sources
     *
     * The sources this person has used, most frequent first, for a picker.
     * A source is only ever the string on each row — there is no catalogue —
     * so the list is whatever has been typed before, and a new one is just a
     * new string on the next POST.
     *
     * @response 200 {"data": ["Salary", "Freelance"]}
     */
    public function sources(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Income::class);

        /*
         * The catalogue, not the income.
         *
         * Read off the income rows until sources became something that could
         * be managed; reading them still would mean a name removed from the
         * catalogue came straight back the moment anything refreshed, and a
         * name added before its first use never appeared at all.
         *
         * Busiest first, counted on the string an income actually carries.
         */
        $uses = Income::query()
            ->forUser($request->user()->id)
            ->whereColumn('incomes.source', 'income_sources.name')
            ->selectRaw('COUNT(*)');

        $sources = IncomeSource::query()
            ->forUser($request->user()->id)
            ->select('name')
            ->selectSub($uses, 'uses')
            ->orderByDesc('uses')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return response()->json(['data' => $sources]);
    }

    /**
     * Monthly income
     *
     * The month's total and count, split by source with the largest first.
     *
     * @queryParam month string YYYY-MM. Anything malformed falls back to the current month rather than erroring. Example: 2026-09
     *
     * @response 200 {"data": {"month": "2026-09", "total": "1200.00", "count": 3, "by_source": [{"source": "Salary", "total": "1000.00"}, {"source": "Freelance", "total": "200.00"}]}}
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Income::class);

        $month = CalendarOptions::resolveMonth($request->query('month'));

        $bySource = Income::query()
            ->forUser($request->user()->id)
            ->inMonth($month->toDateString())
            ->groupBy('source')
            ->selectRaw('source, SUM(amount) as total, COUNT(*) as count')
            ->orderByDesc('total')
            ->orderBy('source')
            ->get();

        return response()->json([
            'data' => [
                'month' => $month->format('Y-m'),
                // Money is a string across this API; sum() returns a float, so
                // format it back to two decimals rather than leaking 12.5.
                'total' => $this->money($bySource->sum('total')),
                'count' => (int) $bySource->sum('count'),
                'by_source' => $bySource
                    ->map(fn ($row) => [
                        'source' => $row->source,
                        'total' => $this->money($row->total),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Get an income
     *
     * @urlParam income string required The income UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198f...", "source": "Salary", "amount": "1200.00", "received_on": "2026-09-01", "note": null}}
     * @response 403 scenario="someone else's income" {"message": "This action is unauthorized."}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(Income $income): IncomeResource
    {
        Gate::authorize('view', $income);

        return new IncomeResource($income);
    }

    /**
     * Record an income
     *
     * The owner always comes from the token — a `user_id` in the payload is
     * ignored, not honoured.
     *
     * @bodyParam source string required Example: Salary
     * @bodyParam amount number required Min 0.01, max 99999999.99. Example: 1200
     * @bodyParam currency string USD (default) or KHR. A riel amount is converted and stored in USD. Example: KHR
     * @bodyParam received_on date required Cannot be in the future. Example: 2026-09-01
     * @bodyParam note string Up to 500 characters. Example: September pay
     *
     * @response 201 {"data": {"uuid": "0198f...", "source": "Salary", "amount": "1200.00", "received_on": "2026-09-01", "note": "September pay"}}
     * @response 403 scenario="token lacks incomes:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="future date" {"message": "You cannot log income in the future.", "errors": {"received_on": ["You cannot log income in the future."]}}
     */
    public function store(IncomeRequest $request): JsonResponse
    {
        Gate::authorize('create', Income::class);

        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $income = DB::transaction(function () use ($request) {
            $income = $request->user()->incomes()->create($request->incomeAttributes());

            // Typing a name straight into the form still teaches it to the
            // picker, so the catalogue is something to tidy rather than to
            // fill in before the app is usable.
            IncomeSource::remember($income->user_id, $income->source);

            return $income;
        });

        return (new IncomeResource($income))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an income
     *
     * Admins holding incomes.manage_all may edit anyone's; everyone else only
     * their own.
     *
     * @urlParam income string required The income UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam source string required Example: Salary
     * @bodyParam amount number required Example: 1200
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam received_on date required Cannot be in the future. Example: 2026-09-01
     * @bodyParam note string Example: September pay
     *
     * @response 200 {"data": {"uuid": "0198f...", "source": "Salary", "amount": "1200.00", "received_on": "2026-09-01", "note": null}}
     * @response 403 scenario="someone else's income" {"message": "This action is unauthorized."}
     */
    public function update(IncomeRequest $request, Income $income): IncomeResource
    {
        Gate::authorize('update', $income);

        DB::transaction(function () use ($request, $income) {
            $income->update($request->incomeAttributes());

            IncomeSource::remember($income->user_id, $income->source);
        });

        return new IncomeResource($income);
    }

    /**
     * Delete an income
     *
     * @urlParam income string required The income UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's income" {"message": "This action is unauthorized."}
     */
    public function destroy(Income $income): JsonResponse
    {
        Gate::authorize('delete', $income);

        DB::transaction(fn () => $income->delete());

        return response()->json([], 204);
    }
}
