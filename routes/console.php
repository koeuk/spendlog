<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Just after midnight, so a rule due today has its row before anyone opens
// the app. The dashboard catches up too, so a missed night is not a missed
// row — this is what keeps reports right for people who do not open it.
Schedule::command('spendlog:run-recurring')->dailyAt('00:05');
