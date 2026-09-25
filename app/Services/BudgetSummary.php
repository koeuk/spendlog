<?php

namespace App\Services;

use App\Enums\Currency;
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
     * The overall row for a month, but only once it is over budget.
     *
     * Every authenticated page asks this, so it must not pay for the
     * per-category work forMonth() does: two indexed reads, and it gives up at
     * the first one when there is no overall budget to be over.
     *
     * Goes through the same row() as everything else on purpose. "Over" is a
     * rounded percentage above 100, not spent > budget — the two disagree for a
     * few cents, and a banner that fires while the page it links to says you are
     * fine is worse than no banner.
     *
     * @return array{spent: float, budget: float, remaining: float, percent: int|null, month: string}|null
     */
    public function overspendFor(User $user, CarbonImmutable $month): ?array
    {
        $start = $month->startOfMonth();

        // The overall budget is stored with a null category_id.
        $amount = Budget::query()
            ->where('user_id', $user->id)
            ->whereNull('category_id')
            ->whereDate('month', $start->toDateString())
            ->value('amount');

        if ($amount === null) {
            return null;
        }

        $spent = (float) Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [
                $start->toDateString(),
                $start->endOfMonth()->toDateString(),
            ])
            ->sum('price');

        $row = $this->row(spent: $spent, amount: (float) $amount);

        if ($row['status'] !== 'over') {
            return null;
        }

        return [...$row, 'month' => $start->format('Y-m')];
    }

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
        $status = $this->status($spent, $amount);
        $percent = $this->percent($spent, $amount);

        return [
            ...$extra,
            // Rounded at the column's own scale, not to cents: the page shows
            // these figures in riel as well as dollars, and snapping to cents
            // first would put a ៛100 spend back at ៛82 — the very rounding the
            // four-place column exists to avoid. The view formats to cents.
            'spent' => round($spent, Currency::SCALE),
            'budget' => $amount,
            'remaining' => $amount !== null ? round($amount - $spent, Currency::SCALE) : null,
            'percent' => $percent,
            // Capped so the bar never overflows its track; percent keeps the
            // truth. A $0 budget that has been spent against has no ratio to
            // cap, and a full track is the honest picture of it.
            'bar_percent' => match (true) {
                $percent !== null => min($percent, 100),
                $status === 'over' => 100,
                default => 0,
            },
            'status' => $status,
        ];
    }

    /**
     * How much of the budget is used, or null when there is no ratio to give.
     *
     * Null in two different situations, which `status` tells apart: no budget
     * is set at all, or a budget of exactly $0 has been spent against — "x% of
     * nothing" has no value, so the status carries that meaning instead. The
     * pages already guard on `percent !== null` before printing it.
     */
    private function percent(float $spent, ?float $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        if ($amount <= 0.0) {
            // Nothing budgeted and nothing spent is the budget met exactly.
            return $spent > 0 ? null : 0;
        }

        return (int) round(($spent / $amount) * 100);
    }

    /**
     * Read off the amounts rather than the percentage.
     *
     * A budget of exactly $0 is a real budget — Currency::minimumInput() allows
     * it for dollars on purpose, as a deliberate "nothing budgeted for this" —
     * so any spend against one is over. Deriving the status from the percentage
     * collapsed it into 'none' instead, which said "no budget set" about a row
     * whose `remaining` was already negative, and kept the over-budget banner
     * from ever firing for it.
     */
    private function status(float $spent, ?float $amount): string
    {
        if ($amount === null) {
            return 'none';
        }

        if ($amount <= 0.0) {
            return $spent > 0 ? 'over' : 'ok';
        }

        $percent = (int) round(($spent / $amount) * 100);

        return match (true) {
            $percent > 100 => 'over',
            $percent >= self::WARNING_AT => 'warning',
            default => 'ok',
        };
    }
}
