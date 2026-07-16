<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Spent vs budget" math, shared by the Budgets page, the Dashboard, and
 * (later) the API — so the rules live in exactly one place.
 */
class BudgetSummary
{
    /** Percentage thresholds that flip the progress bar colour. */
    private const WARNING_AT = 80;

    /**
     * Per-category spend and budget for one user in one month.
     *
     * @return array{month: string, overall: array, categories: array}
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();

        $spendByCategory = $this->spendByCategory($user, $start);
        $budgets = $this->budgetsByCategoryId($user, $start);

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'color', 'icon'])
            ->map(function (Category $category) use ($spendByCategory, $budgets) {
                $spent = (float) ($spendByCategory[$category->id] ?? 0);
                $budget = $budgets->get($category->id);

                return $this->row(
                    spent: $spent,
                    amount: $budget?->amount !== null ? (float) $budget->amount : null,
                    extra: [
                        'uuid' => $category->uuid,
                        'name' => $category->name,
                        'color' => $category->color?->value,
                        'icon' => $category->icon?->value,
                        'budget_uuid' => $budget?->uuid,
                    ],
                );
            })
            ->values()
            ->all();

        // The overall budget is stored with a null category_id.
        $overallBudget = $budgets->get(0);
        $totalSpent = (float) $spendByCategory->sum();

        return [
            'month' => $start->format('Y-m'),
            'overall' => $this->row(
                spent: $totalSpent,
                amount: $overallBudget?->amount !== null ? (float) $overallBudget->amount : null,
                extra: ['budget_uuid' => $overallBudget?->uuid],
            ),
            'categories' => $categories,
        ];
    }

    /**
     * Spend totals keyed by category id.
     *
     * @return Collection<int, float>
     */
    private function spendByCategory(User $user, CarbonImmutable $start): Collection
    {
        return Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [
                $start->toDateString(),
                $start->endOfMonth()->toDateString(),
            ])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(price) as total')
            ->pluck('total', 'category_id');
    }

    /**
     * Budgets keyed by category id, with the overall budget under key 0.
     *
     * @return Collection<int, Budget>
     */
    private function budgetsByCategoryId(User $user, CarbonImmutable $start): Collection
    {
        return Budget::query()
            ->where('user_id', $user->id)
            ->whereDate('month', $start->toDateString())
            ->get()
            ->keyBy(fn (Budget $budget) => $budget->category_id ?? 0);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function row(float $spent, ?float $amount, array $extra = []): array
    {
        $percent = $amount > 0 ? (int) round(($spent / $amount) * 100) : null;

        return [
            ...$extra,
            'spent' => round($spent, 2),
            'budget' => $amount,
            'remaining' => $amount !== null ? round($amount - $spent, 2) : null,
            'percent' => $percent,
            // Capped so the bar never overflows its track; percent keeps the truth.
            'bar_percent' => $percent !== null ? min($percent, 100) : 0,
            'status' => $this->status($percent),
        ];
    }

    private function status(?int $percent): string
    {
        return match (true) {
            $percent === null => 'none',
            $percent > 100 => 'over',
            $percent >= self::WARNING_AT => 'warning',
            default => 'ok',
        };
    }
}
