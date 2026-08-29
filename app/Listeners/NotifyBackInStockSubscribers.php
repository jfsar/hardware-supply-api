<?php

namespace App\Listeners;

use App\Events\InventoryBecameAvailable;
use App\Jobs\ProcessBackInStockNotifications;

class NotifyBackInStockSubscribers
{
    /**
     * Queue the exactly-once fan-out for the now-available variant.
     */
    public function handle(InventoryBecameAvailable $event): void
    {
        ProcessBackInStockNotifications::dispatch($event->variant->getKey())
            ->onQueue('notifications');
    }
}
