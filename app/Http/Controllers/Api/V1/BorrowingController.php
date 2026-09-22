<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BorrowingRepaymentRequest;
use App\Http\Requests\BorrowingRequest;
use App\Http\Resources\BorrowingRepaymentResource;
use App\Http\Resources\BorrowingResource;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Services\BorrowingSummary;
use App\Support\Concerns\ClampsApiPageSize;
use App\Support\Concerns\FormatsMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Borrowing
 *
 * Money borrowed from someone — a friend, family, a bank — and the
 * repayments against it. What is still owed is always `amount − repaid`,
 * derived from the ledger and never stored. Every listing is scoped to the
 * caller's own rows; `borrowings.manage_all` only lets an admin reach a
 * single row by UUID.
 *
 * `lender_type` is one of `friend`, `family`, `bank`, `employer`, `other`.
 *
 * @authenticated
 */
class BorrowingController extends Controller
{
    use ClampsApiPageSize, FormatsMoney;

    public function __construct(private readonly BorrowingSummary $summary) {}

    /**
     * List borrowings
     *
     * Paginated. Still-owed rows first, then newest borrowed. Each row carries
     * `repayments_count`; the ledger itself comes with a single borrowing.
     *
     * @queryParam filter[lender] string Partial match on the lender's name. Example: mom
     * @queryParam filter[type] string One lender type. Example: family
     * @queryParam status string open (still owed), settled (paid back) or all. Default all. Example: open
     * @queryParam sort string borrowed_on, amount, due_on or lender. Prefix with - to reverse. Example: -amount
     * @queryParam per_page int Default 50, clamped to 100. Example: 25
     *
     * @response 200 {"data": [{"uuid": "0198f...", "lender": "Mom", "lender_type": "family", "amount": "200.00", "repaid": "50.00", "remaining": "150.00", "percent_repaid": 25, "settled": false, "overdue": false, "borrowed_on": "2026-09-01", "due_on": "2026-12-01", "note": null, "repayments_count": 1, "created_at": "2026-09-01T10:00:00+00:00", "updated_at": "2026-09-01T10:00:00+00:00"}], "links": {"first": "...", "last": "...", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 50, "total": 1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewAny', Borrowing::class);

        $query = QueryBuilder::for(Borrowing::class)
            ->withRepaid()
            ->withCount('repayments')
            // Applied first so no filter can widen it beyond the owner's rows.
            ->where('user_id', $request->user()->id);

        // Still-owed rows lead the mixed list. Ordered before the spatie
        // sorts, which apply as declared — after them it would only break
        // ties. Mirrors the web BorrowingController.
        $status = $request->query('status');

        if ($status === 'open') {
            $query->open();
        } elseif ($status === 'settled') {
            $query->settled();
        } else {
            $query->orderByRaw('CASE WHEN '.Borrowing::remainingSql().' > 0 THEN 0 ELSE 1 END');
        }

        return BorrowingResource::collection(
            $query
                ->allowedFilters(
                    AllowedFilter::partial('lender'),
                    AllowedFilter::exact('type', 'lender_type'),
                )
                ->allowedSorts('borrowed_on', 'amount', 'due_on', 'lender')
                ->defaultSort('-borrowed_on', '-id')
                ->paginate($this->perPage($request))
                ->withQueryString()
        );
    }

    /**
     * Borrowing summary
     *
     * What is still owed across everything, all time, and to which kinds of
     * lender. `by_lender_type` lists only kinds with something outstanding,
     * largest first.
     *
     * @response 200 {"data": {"outstanding": "350.00", "borrowed": "600.00", "repaid": "250.00", "open_count": 2, "settled_count": 1, "overdue_count": 1, "by_lender_type": [{"lender_type": "family", "label": "Family", "outstanding": "200.00", "count": 1}, {"lender_type": "friend", "label": "Friend", "outstanding": "150.00", "count": 1}]}}
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Borrowing::class);

        $summary = $this->summary->forUser($request->user());

        return response()->json([
            'data' => [
                ...$summary,
                // Money is a string across this API; the service deals in
                // floats like BudgetSummary does.
                'outstanding' => $this->money($summary['outstanding']),
                'borrowed' => $this->money($summary['borrowed']),
                'repaid' => $this->money($summary['repaid']),
                'by_lender_type' => array_map(fn (array $row) => [
                    ...$row,
                    'outstanding' => $this->money($row['outstanding']),
                ], $summary['by_lender_type']),
            ],
        ]);
    }

    /**
     * Lenders
     *
     * The lender names this person has used, most frequent first, for a
     * picker — and the fixed list of lender types with their labels in the
     * app locale.
     *
     * @response 200 {"data": {"lenders": ["Mom", "Sokha"], "types": [{"value": "friend", "label": "Friend"}, {"value": "family", "label": "Family"}]}}
     */
    public function lenders(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Borrowing::class);

        $lenders = Borrowing::query()
            ->forUser($request->user()->id)
            ->groupBy('lender')
            ->selectRaw('lender, COUNT(*) as uses')
            ->orderByDesc('uses')
            ->orderBy('lender')
            ->pluck('lender')
            ->all();

        return response()->json([
            'data' => [
                'lenders' => $lenders,
                'types' => LenderType::options(),
            ],
        ]);
    }

    /**
     * Get a borrowing
     *
     * With its ledger of repayments, newest first.
     *
     * @urlParam borrowing string required The borrowing UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198f...", "lender": "Mom", "lender_type": "family", "amount": "200.00", "repaid": "50.00", "remaining": "150.00", "percent_repaid": 25, "settled": false, "overdue": false, "borrowed_on": "2026-09-01", "due_on": null, "note": null, "repayments": [{"uuid": "0198e...", "amount": "50.00", "paid_on": "2026-09-10", "note": null, "created_at": "2026-09-10T10:00:00+00:00"}]}}
     * @response 403 scenario="someone else's borrowing" {"message": "This action is unauthorized."}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(Borrowing $borrowing): BorrowingResource
    {
        Gate::authorize('view', $borrowing);

        return new BorrowingResource(
            $borrowing->load(['repayments' => fn ($query) => $query->orderByDesc('paid_on')->orderByDesc('id')])
        );
    }

    /**
     * Record a borrowing
     *
     * The owner always comes from the token — a `user_id` in the payload is
     * ignored, not honoured.
     *
     * @bodyParam lender string required Who lent it. Example: Mom
     * @bodyParam lender_type string required friend, family, bank, employer or other. Example: family
     * @bodyParam amount number required Min 0.01, max 99999999.99. Example: 200
     * @bodyParam currency string USD (default) or KHR. A riel amount is converted and stored in USD. Example: KHR
     * @bodyParam borrowed_on date required Cannot be in the future. Example: 2026-09-01
     * @bodyParam due_on date Optional. Not before borrowed_on. Example: 2026-12-01
     * @bodyParam note string Up to 500 characters. Example: For the motorbike repair
     *
     * @response 201 {"data": {"uuid": "0198f...", "lender": "Mom", "lender_type": "family", "amount": "200.00", "repaid": "0.00", "remaining": "200.00", "percent_repaid": 0, "settled": false, "overdue": false, "borrowed_on": "2026-09-01", "due_on": "2026-12-01", "note": null}}
     * @response 403 scenario="token lacks borrowings:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="due before borrowed" {"message": "The due date cannot be before the day it was borrowed.", "errors": {"due_on": ["The due date cannot be before the day it was borrowed."]}}
     */
    public function store(BorrowingRequest $request): JsonResponse
    {
        Gate::authorize('create', Borrowing::class);

        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $borrowing = DB::transaction(
            fn () => $request->user()->borrowings()->create($request->borrowingAttributes())
        );

        return (new BorrowingResource($borrowing))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a borrowing
     *
     * Takes the full shape, like every PATCH here. The amount cannot drop
     * below what has already been repaid against it.
     *
     * @urlParam borrowing string required The borrowing UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam lender string required Example: Mom
     * @bodyParam lender_type string required Example: family
     * @bodyParam amount number required Example: 200
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam borrowed_on date required Cannot be in the future. Example: 2026-09-01
     * @bodyParam due_on date Optional. Example: 2026-12-01
     * @bodyParam note string Example: For the motorbike repair
     *
     * @response 200 {"data": {"uuid": "0198f...", "lender": "Mom", "lender_type": "family", "amount": "200.00", "repaid": "50.00", "remaining": "150.00"}}
     * @response 403 scenario="someone else's borrowing" {"message": "This action is unauthorized."}
     * @response 422 scenario="amount below what is repaid" {"message": "The amount cannot be less than what has already been repaid ($50.00).", "errors": {"amount": ["The amount cannot be less than what has already been repaid ($50.00)."]}}
     */
    public function update(BorrowingRequest $request, Borrowing $borrowing): BorrowingResource
    {
        Gate::authorize('update', $borrowing);

        $attributes = $request->borrowingAttributes();

        DB::transaction(function () use ($borrowing, $attributes) {
            $repaid = $borrowing->repaid();

            if ($attributes['amount'] < $repaid) {
                throw ValidationException::withMessages([
                    'amount' => __('The amount cannot be less than what has already been repaid (:amount).', [
                        'amount' => '$'.number_format($repaid, 2),
                    ]),
                ]);
            }

            $borrowing->update($attributes);
        });

        return new BorrowingResource($borrowing->fresh());
    }

    /**
     * Delete a borrowing
     *
     * The repayments against it go with it.
     *
     * @urlParam borrowing string required The borrowing UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's borrowing" {"message": "This action is unauthorized."}
     */
    public function destroy(Borrowing $borrowing): JsonResponse
    {
        Gate::authorize('delete', $borrowing);

        DB::transaction(fn () => $borrowing->delete());

        return response()->json([], 204);
    }

    /**
     * Record a repayment
     *
     * Money paid back against one borrowing. Capped at what is still owed —
     * checked under a row lock, so two racing repayments cannot both fit.
     * Authorised as an update of the borrowing.
     *
     * @urlParam borrowing string required The borrowing UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam amount number required Min 0.01. Example: 50
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam paid_on date required Not in the future, not before borrowed_on. Example: 2026-09-10
     * @bodyParam note string Up to 500 characters. Example: First half
     *
     * @response 201 {"data": {"uuid": "0198e...", "amount": "50.00", "paid_on": "2026-09-10", "note": "First half", "created_at": "2026-09-10T10:00:00+00:00"}}
     * @response 422 scenario="more than is owed" {"message": "You cannot repay more than is still owed ($150.00).", "errors": {"amount": ["You cannot repay more than is still owed ($150.00)."]}}
     */
    public function storeRepayment(BorrowingRepaymentRequest $request, Borrowing $borrowing): JsonResponse
    {
        Gate::authorize('update', $borrowing);

        $repayment = DB::transaction(function () use ($request, $borrowing) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();

            if ($request->usdAmount() > $locked->remaining()) {
                throw ValidationException::withMessages([
                    'amount' => __('You cannot repay more than is still owed (:amount).', [
                        'amount' => '$'.number_format($locked->remaining(), 2),
                    ]),
                ]);
            }

            $repayment = $locked->repayments()->make($request->repaymentAttributes());
            // From the borrowing, never the token: the ledger line belongs to
            // whoever owes the money, even when an admin records it for them.
            $repayment->user()->associate($locked->user_id);
            $repayment->save();

            return $repayment;
        });

        return (new BorrowingRepaymentResource($repayment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a repayment
     *
     * A repayment under a borrowing it does not belong to is a 404.
     *
     * @urlParam borrowing string required The borrowing UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     * @urlParam repayment string required The repayment UUID. Example: 0198e1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     */
    public function destroyRepayment(Borrowing $borrowing, BorrowingRepayment $repayment): JsonResponse
    {
        Gate::authorize('update', $borrowing);

        abort_unless($repayment->borrowing_id === $borrowing->id, 404);

        DB::transaction(fn () => $repayment->delete());

        return response()->json([], 204);
    }
}
