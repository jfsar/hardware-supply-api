<?php

namespace App\Listeners;

use App\Events\ShipmentDelivered;
use App\Models\User;
use App\Notifications\Fulfillment\ShipmentDelivered as ShipmentDeliveredNotification;
use App\Services\Notifications\NotificationPreferenceGate;

/**
 * Queues the delivered email for registered customers, honoring their
 * notification preferences (Phase 6 Task 6). Guest orders have no
 * notifiable account; Phase 7 adds guest channels.
 */
class SendShipmentDeliveredNotification
{
    public function __construct(protected NotificationPreferenceGate $preferenceGate) {}

    public function handle(ShipmentDelivered $event): void
    {
        /** @var User|null $user */
        $user = $event->shipment->order->user;

        if ($user === null) {
            return;
        }

        if (! ($this->preferenceGate)->allows($user, 'order_updates')) {
            return;
        }

        $user->notify(new ShipmentDeliveredNotification($event->shipment));
    }
}
