<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavingsEntryRequest;
use App\Http\Requests\SavingsPlanRequest;
use App\Http\Resources\SavingsEntryResource;
use App\Http\Resources\SavingsPlanResource;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
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
 * A month at a time, like budgets: a **plan** says how much to put aside this
 * month, and the **entries** are the money that actually moved. What is saved
 * is always the sum of the entries — nothing is stored that could drift from
 * it — and a withdrawal can never take out more than the all-time balance, so
 * that balance is never negative.
 *
 * @authenticated
 */
class SavingsController extends Controller
{
    public function __construct(private readonly SavingsSummary $summary) {}

    /**
     * Savings summary
     *
     * What went aside this month against what was planned, plus the all-time
     * balance — the headline figure, since savings carry over between months.
     *
     * `percent` is capped at 100 so a bar cannot overflow its track;
     * `percent_raw` keeps the truth. `status` is `ok` | `close` (>=80) |
     * `met` (>=100).
     *
     * @queryParam month string YYYY-MM. Anything malformed falls back to the current month rather than erroring. Example: 2026-09
     *
     * @response 200 {"data": {"month": "2026-09", "planned": "100.00", "saved_this_month": "60.00", "remaining": "40.00", "percent": 60, "percent_raw": 60, "status": "ok", "total_saved": "1240.00", "entries_count": 2}}
     */
    public function summary(Request $request): JsonResponse
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewAny', SavingsPlan::class);

        $summary = $this->summary->forMonth(
            $request->user(),
            CalendarOptions::resolveMonth($request->query('month')),
        );

        return response()->json([
            'data' => [
                ...$summary,
                // Money is a string across this API; the service deals in
                // floats like BudgetSummary does.
                'planned' => $this->money($summary['planned']),
                'saved_this_month' => $this->money($summary['saved_this_month']),
                'remaining' => $this->money($summary['remaining']),
                'total_saved' => $this->money($summary['total_saved']),
            ],
        ]);
    }

    /**
     * List the month's entries
     *
     * Every deposit and withdrawal dated inside one month, newest first. Not
     * paginated — a month holds a handful of these.
     *
     * @queryParam month string YYYY-MM. Defaults to the current month. Example: 2026-09
     *
     * @response 200 {"data": [{"uuid": "0198e...", "type": "deposit", "amount": "60.00", "saved_on": "2026-09-05", "note": null, "created_at": "2026-09-05T10:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SavingsEntry::class);

        $month = CalendarOptions::resolveMonth($request->query('month'));

        $entries = SavingsEntry::query()
            ->forUser($request->user()->id)
            ->inMonth($month->toDateString())
            ->orderByDesc('saved_on')
            ->orderByDesc('id')
            ->get();

        return SavingsEntryResource::collection($entries);
    }

    /**
     * Get the month's plan
     *
     * The stored row, or `null` when nothing was planned for that month —
     * which is different from a plan of `"0.00"`.
     *
     * @queryParam month string YYYY-MM. Defaults to the current month. Example: 2026-09
     *
     * @response 200 {"data": {"uuid": "0198d...", "month": "2026-09", "amount": "100.00", "created_at": "2026-09-01T10:00:00+00:00", "updated_at": "2026-09-01T10:00:00+00:00"}}
     * @response 200 scenario="no plan for that month" {"data": null}
     */
    public function plan(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SavingsPlan::class);

        $plan = $this->summary->planFor(
            $request->user(),
            CalendarOptions::resolveMonth($request->query('month')),
        );

        return response()->json([
            'data' => $plan ? new SavingsPlanResource($plan) : null,
        ]);
    }

    /**
     * Set the month's plan
     *
     * Upserts the `(user, month)` slot, so this is idempotent — there is no
     * separate update route and the client never needs to know whether a row
     * already exists. Returns 201 when the month was empty, 200 when it was not.
     *
     * @bodyParam month string required YYYY-MM. A full date is a 422. Example: 2026-09
     * @bodyParam amount number required Min 0, max 99999999.99. Example: 100
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     *
     * @response 201 scenario="month was empty" {"data": {"uuid": "0198d...", "month": "2026-09", "amount": "100.00"}}
     * @response 200 scenario="month already planned" {"data": {"uuid": "0198d...", "month": "2026-09", "amount": "150.00"}}
     * @response 403 scenario="token lacks savings:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="month was a full date" {"message": "The month must look like 2026-07.", "errors": {"month": ["The month must look like 2026-07."]}}
     */
    public function storePlan(SavingsPlanRequest $request): JsonResponse
    {
        Gate::authorize('create', SavingsPlan::class);

        $attributes = $request->planAttributes();

        // Written through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $plan = DB::transaction(fn () => $request->user()->savingsPlans()->updateOrCreate(
            ['month' => $attributes['month']],
            ['amount' => $attributes['amount']],
        ));

        return (new SavingsPlanResource($plan))
            ->response()
            // 201 only when the month was empty; a re-set is a 200.
            ->setStatusCode($plan->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Clear a plan
     *
     * The month's entries are untouched — the money stays, only the intention
     * goes.
     *
     * @urlParam plan string required The plan UUID. Example: 0198d1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's plan" {"message": "This action is unauthorized."}
     */
    public function destroyPlan(SavingsPlan $plan): JsonResponse
    {
        Gate::authorize('delete', $plan);

        DB::transaction(fn () => $plan->delete());

        return response()->json([], 204);
    }

    /**
     * Deposit or withdraw
     *
     * Send a positive `amount` and a `type`; the ledger stores the sign. A
     * withdrawal larger than the **all-time** balance is a 422 on `amount` —
     * money saved in September can be withdrawn in October.
     *
     * @bodyParam type string required deposit or withdraw. Example: deposit
     * @bodyParam amount number required Always positive. Min 0.01. Example: 50
     * @bodyParam currency string USD (default) or KHR. Example: KHR
     * @bodyParam saved_on date required Cannot be in the future. Example: 2026-09-05
     * @bodyParam note string Up to 500 characters. Example: Leftover from September
     *
     * @response 201 {"data": {"uuid": "0198e...", "type": "deposit", "amount": "50.00", "saved_on": "2026-09-05", "note": null, "created_at": "2026-09-05T10:00:00+00:00"}}
     * @response 403 scenario="token lacks savings:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="withdrawing more than is saved" {"message": "You cannot withdraw more than is saved.", "errors": {"amount": ["You cannot withdraw more than is saved."]}}
     */
    public function storeEntry(SavingsEntryRequest $request): JsonResponse
    {
        Gate::authorize('create', SavingsEntry::class);

        $user = $request->user();

        $entry = DB::transaction(function () use ($request, $user) {
            $this->guardWithdrawal($request, $user);

            return $user->savingsEntries()->create($request->entryAttributes());
        });

        return (new SavingsEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Edit an entry
     *
     * The full shape, like the other PATCH endpoints — an omitted `note`
     * clears it. The withdrawal ceiling is the balance *without* this entry,
     * so turning a $20 withdrawal into a $30 one is checked against what would
     * be there if the $20 line had never existed.
     *
     * @urlParam entry string required The entry UUID. Example: 0198e1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198e...", "type": "withdraw", "amount": "30.00", "saved_on": "2026-09-06", "note": "Rainy day", "created_at": "2026-09-05T10:00:00+00:00"}}
     * @response 403 scenario="someone else's entry" {"message": "This action is unauthorized."}
     * @response 422 scenario="withdrawing more than is saved" {"message": "You cannot withdraw more than is saved.", "errors": {"amount": ["You cannot withdraw more than is saved."]}}
     */
    public function updateEntry(SavingsEntryRequest $request, SavingsEntry $entry): SavingsEntryResource
    {
        Gate::authorize('update', $entry);

        DB::transaction(function () use ($request, $entry) {
            // The row being edited is not part of its own ceiling: it is about
            // to be replaced, so what it currently contributes comes off the
            // balance first.
            $this->guardWithdrawal($request, $entry->user, (float) $entry->amount);

            $entry->update($request->entryAttributes());
        });

        return new SavingsEntryResource($entry);
    }

    /**
     * Delete an entry
     *
     * @urlParam entry string required The entry UUID. Example: 0198e1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's entry" {"message": "This action is unauthorized."}
     */
    public function destroyEntry(SavingsEntry $entry): JsonResponse
    {
        Gate::authorize('delete', $entry);

        DB::transaction(fn () => $entry->delete());

        return response()->json([], 204);
    }

    /**
     * Refuse a withdrawal larger than what is actually there.
     *
     * Under a row-level lock on the account, so two withdrawals racing each
     * other cannot both read the same balance and together take out more than
     * was saved. There is no parent row to lock any more — the account is the
     * thing the balance belongs to.
     *
     * @param  float  $excluding  A signed amount already in the balance that is
     *                            about to be replaced (an edit), or 0 for a new entry.
     */
    private function guardWithdrawal(SavingsEntryRequest $request, User $user, float $excluding = 0.0): void
    {
        if (! $request->isWithdrawal()) {
            return;
        }

        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if (! $this->summary->canWithdraw($locked, $request->usdAmount() + $excluding)) {
            throw ValidationException::withMessages([
                'amount' => __('You cannot withdraw more than is saved.'),
            ]);
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
