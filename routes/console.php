<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('seat-holds:prune')->everyFiveMinutes();
Schedule::command('transfer-reservations:prune')->hourly();
Schedule::command('return-changes:void-expired')->daily();
Schedule::command('guides:provision-recurring')->daily();
Schedule::command('reservations:send-day-before-reminders')->dailyAt('09:00');
