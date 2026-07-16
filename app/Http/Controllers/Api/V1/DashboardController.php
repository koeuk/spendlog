<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetSummaryResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Dashboard
 *
 * @authenticated
 */
class DashboardController extends Controller
{
    /** How many expenses the "recent" list returns. */
    private const RECENT_LIMIT = 8;

    public function __construct(private readonly BudgetSummary $summary) {}

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
     * @response 403 scenario="token lacks dashboard:read" {"message": "Invalid ability provided."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::now();

        // The month totals and every budget figure come from the same service
        // the web Dashboard uses, so the two clients can never disagree.
        $summary = $this->summary->forMonth($user, $today->startOfMonth());

        return response()->json([
            'data' => [
                'today' => [
                    'date' => $today->toDateString(),
                    'total' => $this->todayTotal($user, $today),
                ],
                'summary' => new BudgetSummaryResource($summary),
                'breakdown' => $this->breakdown($summary),
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
     * Categories that actually saw spend this month, largest first, each with
     * its share of the month's total.
     *
     * @param  array{overall: array, categories: array}  $summary
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(array $summary): array
    {
        $total = (float) $summary['overall']['spent'];

        if ($total <= 0) {
            return [];
        }

        return collect($summary['categories'])
            ->filter(fn (array $category) => $category['spent'] > 0)
            ->sortByDesc('spent')
            ->map(fn (array $category) => [
                'uuid' => $category['uuid'],
                'name' => $category['name'],
                'color' => $category['color'],
                'icon' => $category['icon'],
                'spent' => number_format((float) $category['spent'], 2, '.', ''),
                'share' => round(($category['spent'] / $total) * 100, 1),
            ])
            ->values()
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
