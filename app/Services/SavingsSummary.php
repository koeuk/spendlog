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
 * Two figures do the work: how the month went against its plan, and the
 * all-time balance. The balance is what a withdrawal is checked against,
 * because September's money is still there to take out in October.
 *
 * A withdrawal spends the month's *headroom* first — the part of the plan the
 * deposits have not covered yet — and only bites the deposits once that is
 * gone. Deposit $100 against a $150 plan and $50 of headroom is left, so
 * taking $80 out spends that $50 and then $30 of the deposit: $70 saved.
 * Take only $50 out and the deposit is untouched: $100 saved.
 *
 * The two simpler rules were both tried and both rejected. Deposits alone let
 * a month drawn right back down still read as fully saved. The plain net made
 * every withdrawal bite the deposit immediately, so a $150 plan with $100 in
 * and $80 out read $20 — as though the $50 never asked for was money lost.
 * Headroom is what is left once you say that a withdrawal first cancels the
 * saving you had not done yet.
 *
 * With no plan there is no headroom, so this degrades to the plain net, which
 * is the only thing "how did the month go" can mean without a target.
 *
 * Moving this changes what a savings plan means. It is not arithmetic.
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

    /** Everything paid into savings in one month. Never negative. */
    public function depositedInMonth(User $user, CarbonImmutable $month): float
    {
        return $this->sumInMonth($user, $month, deposits: true);
    }

    /**
     * Everything taken back out in one month, as a positive magnitude — the
     * column stores withdrawals negative, and a caller asking "how much came
     * out" wants a number it can subtract.
     */
    public function withdrawnInMonth(User $user, CarbonImmutable $month): float
    {
        return abs($this->sumInMonth($user, $month, deposits: false));
    }

    /**
     * How much of the month's plan is standing, after what came back out.
     *
     * A withdrawal spends the headroom — the part of the plan not yet covered
     * by deposits — before it touches the deposits themselves. See the class
     * docblock for why, and for what happens with no plan.
     *
     * Floored at zero. A month drawn further down than it ever put in has
     * saved nothing, not a negative amount, and "-$10 saved, -7% of your plan"
     * reads as a broken figure rather than an honest one. Nothing is hidden by
     * the floor: the withdrawals are in the month's entry list and
     * totalSaved() carries the real balance.
     */
    public function savedInMonth(User $user, CarbonImmutable $month, float $planned = 0.0): float
    {
        $deposited = $this->depositedInMonth($user, $month);
        $withdrawn = $this->withdrawnInMonth($user, $month);

        // Saving that was planned but never done. A withdrawal cancels this
        // before it undoes anything actually put aside; with no plan, or a
        // plan already covered, there is none and the withdrawal bites in full.
        $headroom = max(0.0, $planned - $deposited);

        $bite = max(0.0, $withdrawn - $headroom);

        return max(0.0, round($deposited - $bite, Currency::SCALE));
    }

    /** One side of the month's ledger; the column's sign is the only filter. */
    private function sumInMonth(User $user, CarbonImmutable $month, bool $deposits): float
    {
        $start = $month->startOfMonth();

        $total = SavingsEntry::query()
            ->forUser($user->id)
            ->inMonth($start->toDateString())
            ->where('amount', $deposits ? '>' : '<', 0)
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
     * as `percent_raw` and the status is read off. It can exceed 100; it never
     * goes below 0, because savedInMonth() is floored there.
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
        $saved = $this->savedInMonth($user, $start, $planned);
        $percentRaw = $this->percentRaw($saved, $planned);

        return [
            'month' => $start->format('Y-m'),
            'planned' => $planned,
            // The plan standing after withdrawals — see savedInMonth().
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
