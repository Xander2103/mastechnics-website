<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention of the form security log (docs/anti-spam.md). Requires the
// scheduler to run on the host: `* * * * * php artisan schedule:run`.
// Without it the command can be run by hand; nothing else depends on it.
Schedule::command('forms:prune-security-log')->dailyAt('03:30');
