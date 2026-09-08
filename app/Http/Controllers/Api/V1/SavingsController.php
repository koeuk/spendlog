<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavingsEntryRequest;
use App\Http\Requests\SavingsGoalRequest;
use App\Http\Resources\SavingsEntryResource;
use App\Http\Resources\SavingsGoalResource;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Services\SavingsSummary;
use App\Support\CalendarOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Savings
 *
 * Goals to save towards, each with a ledger of deposits and withdrawals. A
 * goal's balance is always the sum of its ledger — nothing is stored that
 * could drift from it.
 *
 * @authenticated
 */
class SavingsController extends Controller
{
    /** How much of a goal's ledger the detail view returns. */
    private const ENTRY_LIMIT = 100;

    public function __construct(private readonly SavingsSummary $summary) {}

    /**
     * List goals
     *
     * The caller's own goals, newest first, each with its balance and progress.
     * Not paginated — nobody holds a hundred savings goals.
     *
     * @response 200 {"data": [{"uuid": "0198c...", "name": "Emergency fund", "target_amount": "500.00", "saved": "120.00", "remaining": "380.00", "percent": 24, "reached": false, "deadline": null, "color": "emerald", "created_at": "2026-09-01T10:00:00+00:00", "updated_at": "2026-09-01T10:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewAny', SavingsGoal::class);

        $goals = SavingsGoal::query()
            ->forUser($request->user()->id)
            ->withSaved()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SavingsGoalResource::collection($goals);
    }

    /**
     * Savings summary
     *
     * Totals across every goal, plus what was put aside in one month.
     * `percent` is total saved over total target, capped at 100.
     *
     * @queryParam month string YYYY-MM. Anything malformed falls back to the current month rather than erroring. Example: 2026-09
     *
     * @response 200 {"data": {"month": "2026-09", "total_saved": "320.00", "total_target": "1500.00", "percent": 21, "goals_count": 2, "saved_this_month": "50.00"}}
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SavingsGoal::class);

        $summary = $this->summary->forMonth(
            $request->user(),
            CalendarOptions::resolveMonth($request->query('month')),
        );

        return response()->json([
            'data' => [
                ...$summary,
                // Money is a string across this API; the service deals in
                // floats like BudgetSummary does.
                'total_saved' => $this->money($summary['total_saved']),
                'total_target' => $this->money($summary['total_target']),
                'saved_this_month' => $this->money($summary['saved_this_month']),
            ],
        ]);
    }

    /**
     * Get a goal
     *
     * The goal with its ledger, newest entry first, capped at the latest 100.
     *
     * @urlParam goal string required The goal UUID. Example: 0198c1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198c...", "name": "Emergency fund", "target_amount": "500.00", "saved": "120.00", "remaining": "380.00", "percent": 24, "reached": false, "deadline": null, "color": "emerald", "entries": [{"uuid": "0198e...", "type": "deposit", "amount": "120.00", "saved_on": "2026-09-05", "note": null, "created_at": "2026-09-05T10:00:00+00:00"}]}}
     * @response 403 scenario="someone else's goal" {"message": "This action is unauthorized."}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(SavingsGoal $goal): SavingsGoalResource
    {
        Gate::authorize('view', $goal);

        return new SavingsGoalResource($this->withLedger($goal));
    }

    /**
     * Create a goal
     *
     * @bodyParam name string required Example: Emergency fund
     * @bodyParam target_amount number required Min 0.01, max 99999999.99. Example: 500
     * @bodyParam currency string USD (default) or KHR — applies to target_amount. Example: KHR
     * @bodyParam deadline date Optional; cannot be in the past on create. Example: 2027-01-01
     * @bodyParam color string One of the category palette names. Defaults to the first. Example: emerald
     *
     * @response 201 {"data": {"uuid": "0198c...", "name": "Emergency fund", "target_amount": "500.00", "saved": "0.00", "remaining": "500.00", "percent": 0, "reached": false, "deadline": "2027-01-01", "color": "emerald"}}
     * @response 403 scenario="token lacks savings:write" {"message": "Invalid ability provided."}
     */
    public function store(SavingsGoalRequest $request): JsonResponse
    {
        Gate::authorize('create', SavingsGoal::class);

        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $goal = DB::transaction(
            fn () => $request->user()->savingsGoals()->create($request->goalAttributes())
        );

        return (new SavingsGoalResource($goal))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a goal
     *
     * The deadline may already have passed here — an overdue goal is still
     * editable. Omitting `color` leaves it as it is.
     *
     * @urlParam goal string required The goal UUID. Example: 0198c1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam name string required Example: Emergency fund
     * @bodyParam target_amount number required Example: 600
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam deadline date Example: 2027-01-01
     * @bodyParam color string Example: teal
     *
     * @response 200 {"data": {"uuid": "0198c...", "name": "Emergency fund", "target_amount": "600.00", "saved": "120.00", "remaining": "480.00", "percent": 20, "reached": false, "deadline": "2027-01-01", "color": "teal"}}
     * @response 403 scenario="someone else's goal" {"message": "This action is unauthorized."}
     */
    public function update(SavingsGoalRequest $request, SavingsGoal $goal): SavingsGoalResource
    {
        Gate::authorize('update', $goal);

        DB::transaction(fn () => $goal->update($request->goalAttributes()));

        return new SavingsGoalResource($goal);
    }

    /**
     * Delete a goal
     *
     * The ledger goes with it — the FK is cascadeOnDelete.
     *
     * @urlParam goal string required The goal UUID. Example: 0198c1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's goal" {"message": "This action is unauthorized."}
     */
    public function destroy(SavingsGoal $goal): JsonResponse
    {
        Gate::authorize('delete', $goal);

        DB::transaction(fn () => $goal->delete());

        return response()->json([], 204);
    }

    /**
     * Deposit or withdraw
     *
     * Send a positive `amount` and a `type`; the ledger stores the sign. A
     * withdrawal larger than the goal's balance is a 422 on `amount`.
     *
     * @urlParam goal string required The goal UUID. Example: 0198c1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam type string required deposit or withdraw. Example: deposit
     * @bodyParam amount number required Always positive. Min 0.01. Example: 50
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam saved_on date required Cannot be in the future. Example: 2026-09-05
     * @bodyParam note string Up to 500 characters. Example: Leftover from September
     *
     * @response 201 {"data": {"uuid": "0198e...", "type": "deposit", "amount": "50.00", "saved_on": "2026-09-05", "note": null, "created_at": "2026-09-05T10:00:00+00:00"}}
     * @response 403 scenario="someone else's goal" {"message": "This action is unauthorized."}
     * @response 422 scenario="withdrawing more than is saved" {"message": "You cannot withdraw more than is saved.", "errors": {"amount": ["You cannot withdraw more than is saved."]}}
     */
    public function storeEntry(SavingsEntryRequest $request, SavingsGoal $goal): JsonResponse
    {
        // A deposit or withdrawal changes the goal's balance, so it is an
        // update of the goal — there is no separate entry policy.
        Gate::authorize('update', $goal);

        $entry = DB::transaction(function () use ($request, $goal) {
            // Locked so two withdrawals racing each other cannot both read the
            // same balance and together take out more than was there.
            $locked = SavingsGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();

            if ($request->isWithdrawal()
                && ! $this->summary->canWithdraw($this->summary->saved($locked), $request->usdAmount())) {
                throw ValidationException::withMessages([
                    'amount' => __('You cannot withdraw more than is saved.'),
                ]);
            }

            $entry = $goal->entries()->make($request->entryAttributes());
            // The goal's owner, not the caller: an admin depositing on
            // someone's behalf records it under the account it belongs to.
            $entry->user()->associate($goal->user_id);
            $entry->save();

            return $entry;
        });

        return (new SavingsEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete an entry
     *
     * The entry must belong to the goal in the URL; one from another goal is a
     * 404, not a 403.
     *
     * @urlParam goal string required The goal UUID. Example: 0198c1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     * @urlParam entry string required The entry UUID. Example: 0198e1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's goal" {"message": "This action is unauthorized."}
     * @response 404 scenario="entry belongs to another goal" {"message": "Not found."}
     */
    public function destroyEntry(SavingsGoal $goal, SavingsEntry $entry): JsonResponse
    {
        Gate::authorize('update', $goal);

        DB::transaction(fn () => $entry->delete());

        return response()->json([], 204);
    }

    /**
     * The goal with its most recent entries attached.
     */
    private function withLedger(SavingsGoal $goal): SavingsGoal
    {
        return $goal->load([
            'entries' => fn ($query) => $query
                ->orderByDesc('saved_on')
                ->orderByDesc('id')
                ->limit(self::ENTRY_LIMIT),
        ]);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
