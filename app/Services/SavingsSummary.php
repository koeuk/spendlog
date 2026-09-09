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
 * Two figures do the work: what went in and out *this month*, and the
 * all-time balance. The month is what the plan is measured against; the
 * balance is what a withdrawal is measured against, because September's money
 * is still there to take out in October.
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
     * What actually went aside in one month: deposits minus withdrawals.
     *
     * Signed, so it can be negative — a month where more came out than went in
     * is a real month, and clamping it to zero would hide it.
     */
    public function savedInMonth(User $user, CarbonImmutable $month): float
    {
        $start = $month->startOfMonth();

        $total = SavingsEntry::query()
            ->forUser($user->id)
            ->inMonth($start->toDateString())
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
     * How much of the plan was met, uncapped and possibly negative — the truth,
     * which the API sends as `percent_raw` and the status is read off.
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
