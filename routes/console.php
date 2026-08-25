<?php

use App\Jobs\ReleaseExpiredReservations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired checkout reservations release automatically (FR-INV-007/008).
Schedule::job(new ReleaseExpiredReservations, 'inventory')
    ->everyMinute()
    ->name('inventory:release-expired-reservations')
    ->withoutOverlapping();

// Safety net for a missed minute tick (scheduler downtime, long deploys).
Schedule::job(new ReleaseExpiredReservations, 'inventory')
    ->hourly()
    ->name('inventory:release-expired-reservations-sweep');
