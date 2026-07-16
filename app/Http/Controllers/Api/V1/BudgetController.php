<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetSummary $summary) {}

    /**
     * The budgets themselves. For spent-vs-budget figures use the summary
     * endpoint, which is what the Budgets screen actually renders.
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
     * Spend vs budget for one month — the same service the web Dashboard and
     * Budgets page use, so the three can never disagree.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summary->forMonth(
                $request->user(),
                $this->resolveMonth($request->query('month')),
            ),
        ]);
    }

    /**
     * Set or update a budget. One endpoint, because the caller always knows the
     * (category, month) slot and does not care whether a row exists yet — so
     * this is idempotent and there is no separate update route.
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
