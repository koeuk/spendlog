<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetSummaryResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Services\CategoryBreakdown;
use App\Support\CalendarOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Dashboard
 *
 * @authenticated
 */
class DashboardController extends Controller
{
    /** How many expenses the "recent" list returns. */
    private const RECENT_LIMIT = 8;

    public function __construct(
        private readonly BudgetSummary $summary,
        private readonly CategoryBreakdown $breakdown,
    ) {}

    /**
     * Home screen
     *
     * Everything a home screen needs in one call, rather than four round trips:
     * today's total, this month's summary, the by-category breakdown ranked by
     * spend with each category's share of the month, and the 8 most recent
     * expenses.
     *
     * `breakdown` is empty when nothing was spent this month — the shares would
     * be meaningless.
     *
     * @response 200 {"data": {"today": {"date": "2026-07-16", "total": "12.50"}, "summary": {"month": "2026-07", "overall": {"spent": "75.00", "budget": "200.00", "remaining": "125.00", "percent": 38, "bar_percent": 38, "status": "ok"}, "categories": []}, "breakdown": [{"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils", "spent": "75.00", "share": 75}], "recent": [{"uuid": "0198f...", "item": "Coffee", "price": "4.50", "spent_on": "2026-07-16", "category": {"uuid": "0198a...", "name": "Food"}}]}}
     * @queryParam budget_month string YYYY-MM. Which month's budgets `summary` reports. Defaults to the current month. Example: 2026-06
     * @queryParam breakdown_month string YYYY-MM. Which month `breakdown` splits. Independent of budget_month. Example: 2026-03
     * @response 403 scenario="token lacks dashboard:read" {"message": "Invalid ability provided."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewDashboard');

        $user = $request->user();
        $today = CarbonImmutable::now();

        // Each card carries its own month, matching the web Dashboard: budgets
        // are monthly rows, and the breakdown is asked separately so pointing one
        // at March does not drag the other with it. Both default to this month.
        $budgetMonth = CalendarOptions::resolveMonth($request->query('budget_month'));
        $breakdownMonth = CalendarOptions::resolveMonth($request->query('breakdown_month'));

        // The month totals and every budget figure come from the same service
        // the web Dashboard uses, so the two clients can never disagree.
        $summary = $this->summary->forMonth($user, $budgetMonth);

        return response()->json([
            'data' => [
                'today' => [
                    'date' => $today->toDateString(),
                    'total' => $this->todayTotal($user, $today),
                ],
                // The real current month, so a client can tell "now" apart from
                // whichever month it happens to be browsing.
                'current_month' => $today->format('Y-m'),
                'summary' => new BudgetSummaryResource($summary),
                'budget_month' => $budgetMonth->format('Y-m'),
                'breakdown' => $this->breakdown($user, $breakdownMonth),
                'breakdown_month' => $breakdownMonth->format('Y-m'),
                'recent' => ExpenseResource::collection($this->recent($user)),
            ],
        ]);
    }

    private function todayTotal(User $user, CarbonImmutable $today): string
    {
        $total = Expense::query()
            ->where('user_id', $user->id)
            ->whereDate('spent_on', $today->toDateString())
            ->sum('price');

        // Money is a string across this API; sum() returns a float, so format
        // it back to two decimals rather than leaking 12.5 for 12.50.
        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Categories that saw spend in the chosen month, largest first, each with its
     * share of that month's total.
     *
     * The figures come from the shared CategoryBreakdown service, so this and the
     * web Dashboard cannot answer the same question differently. Only the money
     * type is changed here: amounts are strings across this API, and the service
     * deals in floats.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(User $user, CarbonImmutable $month): array
    {
        return collect($this->breakdown->forMonth($user, $month))
            ->map(fn (array $row) => [
                ...$row,
                'spent' => number_format((float) $row['spent'], 2, '.', ''),
            ])
            ->all();
    }

    /**
     * @return Collection<int, Expense>
     */
    private function recent(User $user)
    {
        return Expense::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }
}
