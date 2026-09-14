<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic cleanup of accumulated data (requires cron running `php artisan schedule:run`).
Schedule::command('taxi:cleanup')->dailyAt('03:30');

// Generate planned commute requests for window date D (22:00–06:00) at 12:00.
Schedule::command('taxi:generate-planned-requests')->dailyAt('12:00');
