<?php

namespace App\Listeners;

use App\Events\PriceDropped;
use App\Jobs\ProcessPriceDropNotifications;

class NotifyPriceDropSubscribers
{
    /**
     * Queue the price-drop fan-out carrying the previous/new amounts.
     */
    public function handle(PriceDropped $event): void
    {
        ProcessPriceDropNotifications::dispatch(
            $event->variant->getKey(),
            $event->previousMinor,
            $event->newMinor,
            $event->currencyCode,
        )->onQueue('notifications');
    }
}
