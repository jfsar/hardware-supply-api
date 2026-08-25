<?php

namespace App\Jobs;

use App\Enums\ReservationStatus;
use App\Models\InventoryReservation;
use App\Services\Inventory\ReleaseStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Release every active reservation past its expiry in ID-chunked batches;
     * each release re-validates under lock inside its own short transaction.
     */
    public function handle(ReleaseStock $release): void
    {
        InventoryReservation::query()
            ->where('status', ReservationStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use ($release): void {
                foreach ($reservations as $reservation) {
                    $release($reservation, expired: true);
                }
            });
    }
}
