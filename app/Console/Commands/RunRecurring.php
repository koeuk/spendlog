<?php

namespace App\Console\Commands;

use App\Services\RecurringRunner;
use Illuminate\Console\Command;

/**
 * The nightly pass over every active recurring rule. Scheduled in
 * routes/console.php; safe to run by hand any number of times, since a run
 * only ever writes what is due and not yet written.
 */
class RunRecurring extends Command
{
    protected $signature = 'spendlog:run-recurring';

    protected $description = 'Create the expense and income rows that recurring rules have due';

    public function handle(RecurringRunner $runner): int
    {
        $created = $runner->runAll();

        $this->info("Created {$created} recurring row(s).");

        return self::SUCCESS;
    }
}
