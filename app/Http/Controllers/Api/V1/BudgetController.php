<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\BudgetSummaryResource;
use App\Models\Budget;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Budgets
 *
 * A budget is one `(category, month)` slot. **Omit `category_uuid` for the
 * overall budget** covering every category.
 *
 * @authenticated
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetSummary $summary) {}

    /**
     * List budgets
     *
     * The stored budgets themselves. For spent-vs-budget figures use the
     * summary endpoint — that is what the Budgets screen renders.
     *
     * @queryParam month string Restrict to one month, as YYYY-MM. Example: 2026-07
     *
     * @response 200 {"data": [{"uuid": "0198b...", "amount": "250.00", "month": "2026-07", "category": {"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils"}, "created_at": "2026-07-16T10:00:00+00:00", "updated_at": "2026-07-16T10:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->budgets()->with('category');

        if ($month = $request->query('month')) {
            $query->whereDate('month', $this->resolveMonth($month)->toDateString());
        }

        return BudgetResource::collection($query->orderBy('month')->get());
    }

    /**
     * Spend vs budget
     *
     * Spend against budget for one month, per category and overall — the same
     * service the web Dashboard and Budgets page use, so the three can never
     * disagree.
     *
     * `status` is `ok` | `warning` (>=80%) | `over` (>100%) | `none` (no budget
     * set). `budget: null` means no budget, which is different from `"0.00"`.
     * `bar_percent` is capped at 100 so a progress bar cannot overflow its
     * track; `percent` keeps the truth.
     *
     * @queryParam month string YYYY-MM. Anything malformed falls back to the current month rather than erroring. Example: 2026-07
     *
     * @response 200 {"data": {"month": "2026-07", "overall": {"budget_uuid": "0198b...", "spent": "106.00", "budget": "100.00", "remaining": "-6.00", "percent": 106, "bar_percent": 100, "status": "over"}, "categories": [{"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils", "budget_uuid": "0198b...", "spent": "106.00", "budget": "100.00", "remaining": "-6.00", "percent": 106, "bar_percent": 100, "status": "over"}]}}
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->summary->forMonth(
            $request->user(),
            $this->resolveMonth($request->query('month')),
        );

        return response()->json([
            'data' => new BudgetSummaryResource($summary),
        ]);
    }

    /**
     * Set a budget
     *
     * Upserts the `(category, month)` slot, so this is idempotent — there is no
     * separate update route and the client never needs to know whether a row
     * already exists. Returns 201 when the slot was empty, 200 when it was not.
     *
     * @bodyParam category_uuid string Omit entirely for the overall budget covering every category. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @bodyParam month string required YYYY-MM. A full date is a 422. Example: 2026-07
     * @bodyParam amount number required Max 99999999.99. Example: 250
     *
     * @response 201 scenario="slot was empty" {"data": {"uuid": "0198b...", "amount": "250.00", "month": "2026-07", "category": {"uuid": "0198a...", "name": "Food"}}}
     * @response 200 scenario="slot already set" {"data": {"uuid": "0198b...", "amount": "400.00", "month": "2026-07", "category": {"uuid": "0198a...", "name": "Food"}}}
     * @response 422 scenario="month was a full date" {"message": "The month must look like 2026-07.", "errors": {"month": ["The month must look like 2026-07."]}}
     */
    public function store(BudgetRequest $request): JsonResponse
    {
        $attributes = $request->budgetAttributes();

        $budget = DB::transaction(fn () => $request->user()->budgets()->updateOrCreate(
            [
                'category_id' => $attributes['category_id'],
                'month' => $attributes['month'],
            ],
            ['amount' => $attributes['amount']],
        ));

        return (new BudgetResource($budget->load('category')))
            ->response()
            // 201 only when the slot was empty; a re-set is a 200.
            ->setStatusCode($budget->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Delete a budget
     *
     * @urlParam budget string required The budget UUID. Example: 0198b1c2-d3e4-7f5a-8b9c-0d1e2f3a4b5c
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's budget" {"message": "This action is unauthorized."}
     */
    public function destroy(Budget $budget): JsonResponse
    {
        Gate::authorize('delete', $budget);

        DB::transaction(fn () => $budget->delete());

        return response()->json([], 204);
    }

    /**
     * Accepts 'YYYY-MM'; anything else falls back to the current month rather
     * than throwing, matching the web controller's behaviour.
     */
    private function resolveMonth(?string $month): CarbonImmutable
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
