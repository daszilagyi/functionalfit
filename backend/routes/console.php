<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// SCHEDULED TASKS
// ============================================

// Generate recurring class occurrences daily at 2:00 AM
Schedule::command('classes:generate-recurring --days=14')
    ->dailyAt('02:00')
    ->timezone('Europe/Budapest')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Send 24-hour reminders daily at 9:00 AM
Schedule::command('reminders:send-daily --hours=24')
    ->dailyAt('09:00')
    ->timezone('Europe/Budapest')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Send 2-hour reminders every hour (only sends if class/event starts within 2h ±1h window)
Schedule::command('reminders:send-daily --hours=2')
    ->hourly()
    ->timezone('Europe/Budapest')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Calculate monthly payouts on the 1st of each month at 3:00 AM
Schedule::command('payouts:calculate-monthly')
    ->monthlyOn(1, '03:00')
    ->timezone('Europe/Budapest')
    ->onOneServer()
    ->withoutOverlapping();

// Clean up old notifications (older than 90 days) weekly on Sundays at 4:00 AM
Schedule::command('model:prune', ['--model' => 'App\\Models\\Notification'])
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->timezone('Europe/Budapest');

// Clean up failed jobs (older than 7 days) daily at 1:00 AM
Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('01:00')
    ->timezone('Europe/Budapest');
