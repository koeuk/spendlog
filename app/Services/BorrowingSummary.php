<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\LenderType;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The headline figures over everything one person has borrowed, shared by
 * the web page and the API so the two cannot disagree.
 *
 * All time, not a month: a debt is not a monthly thing. What matters is
 * what is still owed right now, and to whom.
 */
class BorrowingSummary
{
    /**
     * @return array{
     *     outstanding: float, borrowed: float, repaid: float,
     *     open_count: int, settled_count: int, overdue_count: int,
     *     by_lender_type: array<int, array{lender_type: string, label: string, outstanding: float, count: int}>
     * }
     */
    public function forUser(User $user): array
    {
        // A person's debts are a handful of rows, so they are loaded whole
        // rather than aggregated in SQL — which also keeps "settled" defined in
        // exactly one place, Borrowing::remaining().
        $rows = Borrowing::query()
            ->forUser($user->id)
            ->withRepaid()
            ->get();

        $open = $rows->reject(fn (Borrowing $b) => $b->isSettled());

        return [
            'outstanding' => $this->sum($open, fn (Borrowing $b) => $b->remaining()),
            'borrowed' => $this->sum($rows, fn (Borrowing $b) => (float) $b->amount),
            'repaid' => $this->sum($rows, fn (Borrowing $b) => $b->repaid()),
            'open_count' => $open->count(),
            'settled_count' => $rows->count() - $open->count(),
            'overdue_count' => $open->filter(fn (Borrowing $b) => $b->isOverdue())->count(),
            'by_lender_type' => $this->byLenderType($open),
        ];
    }

    /**
     * What is still owed to each kind of lender, largest first. Only kinds
     * with something outstanding appear.
     *
     * @param  Collection<int, Borrowing>  $open
     * @return array<int, array{lender_type: string, label: string, outstanding: float, count: int}>
     */
    private function byLenderType(Collection $open): array
    {
        return $open
            ->groupBy(fn (Borrowing $b) => $b->lender_type->value)
            ->map(fn (Collection $group, string $type) => [
                'lender_type' => $type,
                'label' => LenderType::from($type)->label(),
                'outstanding' => $this->sum($group, fn (Borrowing $b) => $b->remaining()),
                'count' => $group->count(),
            ])
            // Largest first; ties by label so the order is stable on reload.
            ->sortBy([['outstanding', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Borrowing>  $rows
     */
    private function sum(Collection $rows, callable $of): float
    {
        return round((float) $rows->sum($of), Currency::SCALE);
    }
}
