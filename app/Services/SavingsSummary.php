<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Saved vs planned" maths, in one place — the savings counterpart to
 * BudgetSummary, and shaped like it on purpose so the two cards on the
 * Dashboard cannot answer the same question differently.
 *
 * Two figures do the work: what went *into* savings this month, and the
 * all-time balance. The month's deposits are what the plan is measured
 * against; the balance is what a withdrawal is measured against, because
 * September's money is still there to take out in October.
 *
 * The plan asks "how much will you put aside this month", so only deposits
 * answer it. A withdrawal spends the balance rather than undoing the month's
 * saving, and it shows up in `total_saved` where it belongs — netting it off
 * the month would let one taken-out dollar erase a dollar genuinely put in.
 *
 * This was the net once, and briefly again. The net is the wrong measure here
 * for two reasons: a month that gave back more than it took in reported a
 * negative figure and a negative percentage, and money deposited and then
 * spent within the same month left no trace on the card at all. Both are
 * questions `total_saved` and the entry list answer. Changing this back means
 * changing what a savings plan means, so read that decision before moving it.
 *
 * Deals in floats like BudgetSummary; the resources and controllers format
 * money to strings at the API boundary.
 */
class SavingsSummary
{
    /** Percentages that flip the progress colour. */
    private const CLOSE_AT = 80;

    private const MET_AT = 100;

    /**
     * The month's plan row, or null when nothing was planned.
     *
     * Kept separate from plannedFor() because "no plan" and "a plan of $0" are
     * different things to the endpoints that show or clear one, even though
     * both are 0.00 to the maths.
     */
    public function planFor(User $user, CarbonImmutable $month): ?SavingsPlan
    {
        return SavingsPlan::query()
            ->forUser($user->id)
            ->whereDate('month', $month->startOfMonth()->toDateString())
            ->first();
    }

    /** How much was meant to go aside this month; 0 when no plan is set. */
    public function plannedFor(User $user, CarbonImmutable $month): float
    {
        $amount = SavingsPlan::query()
            ->forUser($user->id)
            ->whereDate('month', $month->startOfMonth()->toDateString())
            ->value('amount');

        return round((float) $amount, Currency::SCALE);
    }

    /**
     * What went aside in one month: the deposits, and only the deposits.
     *
     * Never negative — this is the month's contribution to savings, which is
     * what the plan is set against. What came back out in the same month is a
     * movement of the balance, not a smaller contribution, and it is visible
     * in the entry list and in totalSaved() either way.
     */
    public function savedInMonth(User $user, CarbonImmutable $month): float
    {
        $start = $month->startOfMonth();

        $total = SavingsEntry::query()
            ->forUser($user->id)
            ->inMonth($start->toDateString())
            // Positive rows only: the column is signed, so this is the
            // deposits without needing a type column to ask.
            ->where('amount', '>', 0)
            ->sum('amount');

        return round((float) $total, Currency::SCALE);
    }

    /**
     * The running balance of everything set aside, all time.
     *
     * This is the headline figure, and the one a withdrawal is checked
     * against — never the month's.
     */
    public function totalSaved(User $user): float
    {
        return round((float) SavingsEntry::query()->forUser($user->id)->sum('amount'), Currency::SCALE);
    }

    /** What is left of the month's plan. Never below zero: over-saving is not a debt. */
    public function remaining(float $saved, float $planned): float
    {
        return max(0.0, round($planned - $saved, Currency::SCALE));
    }

    /**
     * How much of the plan was met, uncapped — the truth, which the API sends
     * as `percent_raw` and the status is read off. It can exceed 100; it can
     * no longer go below 0, since savedInMonth() counts deposits only.
     */
    public function percentRaw(float $saved, float $planned): int
    {
        if ($planned <= 0) {
            return 0;
        }

        return (int) round($saved / $planned * 100);
    }

    /** The same figure clamped to 0..100, so a progress bar cannot overflow its track. */
    public function percent(float $saved, float $planned): int
    {
        return max(0, min(100, $this->percentRaw($saved, $planned)));
    }

    /**
     * 'met' once the plan is reached, 'close' from 80% up, 'ok' below.
     *
     * Read off the rounded percentage rather than comparing the amounts, for
     * the reason BudgetSummary gives: a card that says 100% while the status
     * still says "close" is worse than either on its own.
     */
    public function status(int $percentRaw): string
    {
        return match (true) {
            $percentRaw >= self::MET_AT => 'met',
            $percentRaw >= self::CLOSE_AT => 'close',
            default => 'ok',
        };
    }

    /**
     * Whether a withdrawal of $amount fits in what this person holds.
     *
     * Against the all-time balance, not the month's: money saved in September
     * is still there to take out in October. Compared at the column's own
     * scale, so a balance of 50.0000 can be withdrawn in full rather than
     * failing on float noise.
     */
    public function canWithdraw(User $user, float $amount): bool
    {
        return round($this->totalSaved($user) - $amount, Currency::SCALE) >= 0;
    }

    /**
     * Every figure the Savings screen and the Dashboard card render.
     *
     * @return array{month: string, planned: float, saved_this_month: float, remaining: float, percent: int, percent_raw: int, status: string, total_saved: float, entries_count: int}
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();

        $planned = $this->plannedFor($user, $start);
        $saved = $this->savedInMonth($user, $start);
        $percentRaw = $this->percentRaw($saved, $planned);

        return [
            'month' => $start->format('Y-m'),
            'planned' => $planned,
            // Deposits only — see savedInMonth(). The key keeps its name: what
            // was "saved this month" is what was put in this month.
            'saved_this_month' => $saved,
            'remaining' => $this->remaining($saved, $planned),
            'percent' => $this->percent($saved, $planned),
            'percent_raw' => $percentRaw,
            'status' => $this->status($percentRaw),
            'total_saved' => $this->totalSaved($user),
            'entries_count' => SavingsEntry::query()
                ->forUser($user->id)
                ->inMonth($start->toDateString())
                ->count(),
        ];
    }
}
