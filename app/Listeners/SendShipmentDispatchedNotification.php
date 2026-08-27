<?php

namespace App\Listeners;

use App\Events\ShipmentDispatched;
use App\Models\User;
use App\Notifications\Fulfillment\ShipmentDispatched as ShipmentDispatchedNotification;
use App\Services\Notifications\NotificationPreferenceGate;

/**
 * Queues the dispatch email for registered customers, honoring their
 * notification preferences (Phase 6 Task 6). Guest orders have no
 * notifiable account; Phase 7 adds guest channels.
 */
class SendShipmentDispatchedNotification
{
    public function __construct(protected NotificationPreferenceGate $preferenceGate) {}

    public function handle(ShipmentDispatched $event): void
    {
        /** @var User|null $user */
        $user = $event->shipment->order->user;

        if ($user === null) {
            return;
        }

        if (! ($this->preferenceGate)->allows($user, 'order_updates')) {
            return;
        }

        $user->notify(new ShipmentDispatchedNotification($event->shipment));
    }
}
