<?php

use App\Jobs\ProcessAccountDeletion;
use App\Jobs\PruneRecentlyViewedProducts;
use App\Jobs\PurgeExpiredReportExports;
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

// Gateway reconciliation sweep (Phase 5 Task 6): the failure detector for
// provider states that never arrive as webhooks. Nightly, overlap-safe.
Schedule::command('payments:reconcile')
    ->dailyAt('02:30')
    ->name('payments:reconcile')
    ->withoutOverlapping()
    ->onOneServer();

// Recently-viewed retention sweep (Phase 7 Task 3): drop history older than
// the engagement window each morning on the notifications workers.
Schedule::job(new PruneRecentlyViewedProducts, 'notifications')
    ->dailyAt('03:00')
    ->name('engagement:prune-recently-viewed')
    ->withoutOverlapping();

// Report export expiry (Phase 8, FR-RPT-004): purge stored CSVs past their
// download window each morning on the reports workers.
Schedule::job(new PurgeExpiredReportExports, 'reports')
    ->dailyAt('04:00')
    ->name('reports:purge-expired-exports')
    ->withoutOverlapping();

// Account erasure finalization (Phase 8, NFR-PRIV-001/002): anonymize every
// deletion request whose grace window has fully elapsed.
Schedule::job(new ProcessAccountDeletion)
    ->dailyAt('04:30')
    ->name('privacy:process-account-deletions')
    ->withoutOverlapping();
