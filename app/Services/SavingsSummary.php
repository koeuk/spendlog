<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The savings maths, in one place: what a goal holds, how far along it is,
 * and the totals the Savings screen and the Dashboard both render.
 *
 * Deals in floats like BudgetSummary; the resources and controllers format
 * money to strings at the API boundary.
 */
class SavingsSummary
{
    /**
     * What a goal currently holds: SUM(entries.amount), deposits minus
     * withdrawals.
     *
     * Reads the `saved_total` attribute when the goal was loaded through
     * SavingsGoal::withSaved(), so a list pays one query — and sums the
     * ledger itself otherwise, so a goal fetched on its own still answers.
     */
    public function saved(SavingsGoal $goal): float
    {
        $total = $goal->getAttribute('saved_total') ?? $goal->entries()->sum('amount');

        return round((float) $total, Currency::SCALE);
    }

    /** Never below zero: a goal cannot be "over-saved" into negative remaining. */
    public function remaining(float $saved, float $target): float
    {
        return max(0.0, round($target - $saved, Currency::SCALE));
    }

    /**
     * How far along the goal is, 0..100.
     *
     * Zero when there is no target to measure against, and capped at 100 so
     * a bar cannot overflow its track — there is no "truth" beyond reached.
     */
    public function percent(float $saved, float $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round($saved / $target * 100)));
    }

    public function reached(float $saved, float $target): bool
    {
        return $target > 0 && round($saved - $target, Currency::SCALE) >= 0;
    }

    /**
     * Whether a withdrawal of $amount fits in what the goal holds.
     *
     * Compared at the column's own scale, so a balance of 50.0000 can be
     * withdrawn in full rather than failing on float noise.
     */
    public function canWithdraw(float $saved, float $amount): bool
    {
        return round($saved - $amount, Currency::SCALE) >= 0;
    }

    /**
     * The figures across every goal a person holds — what the Dashboard card
     * shows. `percent` is the overall progress, total saved over total target.
     *
     * @return array{total_saved: float, total_target: float, percent: int, goals_count: int}
     */
    public function totals(User $user): array
    {
        $goals = SavingsGoal::query()
            ->forUser($user->id)
            ->withSaved()
            ->get(['id', 'target_amount']);

        $saved = round((float) $goals->sum(fn (SavingsGoal $goal) => $this->saved($goal)), Currency::SCALE);
        $target = round((float) $goals->sum(fn (SavingsGoal $goal) => (float) $goal->target_amount), Currency::SCALE);

        return [
            'total_saved' => $saved,
            'total_target' => $target,
            'percent' => $this->percent($saved, $target),
            'goals_count' => $goals->count(),
        ];
    }

    /**
     * The totals plus what was put aside in one month across every goal,
     * deposits minus withdrawals.
     *
     * @return array{month: string, total_saved: float, total_target: float, percent: int, goals_count: int, saved_this_month: float}
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();

        $thisMonth = SavingsEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('saved_on', [
                $start->toDateString(),
                $start->endOfMonth()->toDateString(),
            ])
            ->sum('amount');

        return [
            'month' => $start->format('Y-m'),
            ...$this->totals($user),
            'saved_this_month' => round((float) $thisMonth, Currency::SCALE),
        ];
    }
}
