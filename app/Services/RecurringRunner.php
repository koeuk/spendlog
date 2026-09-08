<?php

namespace App\Services;

use App\Enums\RecurringKind;
use App\Models\Expense;
use App\Models\Income;
use App\Models\RecurringRule;
use App\Models\User;
use App\Support\TranslatableInput;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns recurring rules into real expense and income rows.
 *
 * The one place a rule is ever materialised, whoever asks: the nightly
 * command, a rule's own create/update, or the dashboard catching up for the
 * caller. A run walks a rule's cursor (next_run_on) forward one occurrence at
 * a time up to today, writing a row per step, and switches the rule off once
 * the cursor passes ends_on.
 *
 * Idempotency is check-before-insert: the dates a rule has already written
 * are read once per run and skipped, and the unique index on
 * (recurring_rule_id, date) stands behind that as the hard guarantee. Each
 * run takes a row lock on the rule inside a transaction, so the cron job and
 * a dashboard call landing together serialise instead of both stepping the
 * cursor from the same place.
 */
class RecurringRunner
{
    /**
     * The most occurrences one run will walk. A daily rule that has not run
     * in years must not stall the request that happens to trip it; the rest
     * is picked up by the next run.
     */
    public const MAX_PER_RUN = 400;

    /** Materialise everything due for one person. What the dashboard calls. */
    public function runDue(User $user): int
    {
        return $this->runEach(
            RecurringRule::query()->forUser($user->id)->due()->pluck('id')
        );
    }

    /** Materialise everything due for everyone. What the nightly command calls. */
    public function runAll(): int
    {
        return $this->runEach(
            RecurringRule::query()->due()->pluck('id')
        );
    }

    /**
     * Materialise one rule's due occurrences, whether or not it is currently
     * due — a rule that starts today gets today's row the moment it is saved.
     *
     * The instance handed in is brought up to date with what the run wrote,
     * so a controller can return it straight into a resource.
     *
     * @return int rows created
     */
    public function run(RecurringRule $rule): int
    {
        return DB::transaction(function () use ($rule) {
            $locked = RecurringRule::query()->whereKey($rule->getKey())->lockForUpdate()->first();

            // Deleted between the caller's read and the lock; nothing to do.
            if ($locked === null) {
                return 0;
            }

            $created = $this->materialise($locked);

            $rule->setRawAttributes($locked->getAttributes(), true);

            return $created;
        });
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function runEach(Collection $ids): int
    {
        $created = 0;

        foreach ($ids as $id) {
            $rule = RecurringRule::query()->find($id);

            if ($rule !== null) {
                $created += $this->run($rule);
            }
        }

        return $created;
    }

    /** The catch-up loop. Runs under the row lock taken by run(). */
    private function materialise(RecurringRule $rule): int
    {
        if (! $rule->active) {
            return 0;
        }

        $today = CarbonImmutable::today();
        $endsOn = $rule->ends_on;
        $anchorDay = $rule->starts_on->day;
        $written = $this->writtenDates($rule);

        $next = $rule->next_run_on;
        $lastCreated = null;
        $created = 0;

        for ($step = 0; $step < self::MAX_PER_RUN; $step++) {
            if ($next->greaterThan($today) || ($endsOn !== null && $next->greaterThan($endsOn))) {
                break;
            }

            if (! $written->contains($next->toDateString())) {
                $this->write($rule, $next);
                $created++;
            }

            $lastCreated = $next;
            $next = $rule->frequency->next($next, $anchorDay);
        }

        $rule->next_run_on = $next;

        if ($lastCreated !== null) {
            $rule->last_run_on = $lastCreated;
        }

        // Past the end: nothing more will ever be due, so drop out of the
        // due() index rather than being re-read every night for nothing.
        if ($endsOn !== null && $next->greaterThan($endsOn)) {
            $rule->active = false;
        }

        $rule->save();

        return $created;
    }

    /**
     * The dates this rule has already written on or after its cursor, so a
     * cursor that was moved back can never double-write a day.
     *
     * @return Collection<int, string>
     */
    private function writtenDates(RecurringRule $rule): Collection
    {
        $column = $rule->kind === RecurringKind::Expense ? 'spent_on' : 'received_on';

        $query = $rule->kind === RecurringKind::Expense ? $rule->expenses() : $rule->incomes();

        return $query
            ->where($column, '>=', $rule->next_run_on->toDateString())
            ->pluck($column)
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString());
    }

    /**
     * One occurrence, as an ordinary row.
     *
     * Saved quietly: the activity log records what a person did, and a rule
     * firing on their behalf is not that — a year's catch-up would otherwise
     * bury the log under rows nobody typed. HasUuids assigns the uuid in the
     * creating event, so it is set by hand here.
     */
    private function write(RecurringRule $rule, CarbonImmutable $on): void
    {
        if ($rule->kind === RecurringKind::Expense) {
            $expense = new Expense([
                'category_id' => $rule->category_id,
                'price' => $rule->amount,
                'spent_on' => $on->toDateString(),
            ]);
            $expense->setTranslations('item', TranslatableInput::toTranslations($rule->title));
            $expense->uuid = $expense->newUniqueId();
            $expense->user_id = $rule->user_id;
            $expense->recurring_rule_id = $rule->id;
            $expense->saveQuietly();

            return;
        }

        $income = new Income([
            'source' => $rule->title,
            'amount' => $rule->amount,
            'received_on' => $on->toDateString(),
            'note' => $rule->note,
        ]);
        $income->uuid = $income->newUniqueId();
        $income->user_id = $rule->user_id;
        $income->recurring_rule_id = $rule->id;
        $income->saveQuietly();
    }
}
