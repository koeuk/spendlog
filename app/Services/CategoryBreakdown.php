<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Where it went" — per-category spend for one user in one month, ranked, with
 * each category's share of that month's total.
 *
 * Shared by the web Dashboard and the API so the two cannot answer the same
 * question differently. They already had drifted: one derived the split from
 * BudgetSummary (which is anchored to a month by design, because budgets are
 * monthly) while the other queried expenses directly, so the same data could
 * come back with different shares and a different sort.
 *
 * Queried directly rather than read off the budget summary on purpose. Deriving
 * it there would tie the card to whichever month the budgets are showing, and
 * would divide figures that BudgetSummary has already rounded — losing a decimal
 * place from every share.
 */
class CategoryBreakdown
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $spend = Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(price) as total')
            ->pluck('total', 'category_id');

        $total = (float) $spend->sum();

        // Nothing spent means every share would be a division by zero, and a
        // list of 0% rows says less than an empty state does.
        if ($total <= 0) {
            return [];
        }

        // Only the categories that appear — an empty row would draw a zero-width
        // bar and take a line for nothing.
        return Category::query()
            ->whereIn('id', $spend->keys())
            ->get(['id', 'uuid', 'name', 'color', 'icon'])
            ->map(function (Category $category) use ($spend, $total) {
                $spent = (float) $spend[$category->id];

                return [
                    'uuid' => $category->uuid,
                    // Read through the accessor, so the active locale resolves —
                    // toArray() would hand back the raw {"en":…,"km":…} map.
                    'name' => $category->name,
                    'color' => $category->color?->value,
                    'icon' => $category->icon?->value,
                    'spent' => round($spent, 2),
                    'share' => round(($spent / $total) * 100, 1),
                ];
            })
            ->sortByDesc('spent')
            ->values()
            ->all();
    }
}
