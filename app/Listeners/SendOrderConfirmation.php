<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\User;
use App\Notifications\Orders\OrderConfirmation;
use App\Services\Notifications\NotificationPreferenceGate;
use Illuminate\Support\Facades\Notification;

/**
 * Queues the order confirmation email for registered customers,
 * honoring their notification preferences (Phase 4 Task 10). Guest
 * orders have no notifiable account; Phase 7 adds guest channels.
 */
class SendOrderConfirmation
{
    public function __construct(protected NotificationPreferenceGate $preferenceGate) {}

    public function handle(OrderCreated $event): void
    {
        /** @var User|null $user */
        $user = $event->order->user;

        if ($user === null) {
            return;
        }

        if (! ($this->preferenceGate)->allows($user, 'order_updates')) {
            return;
        }

        $user->notify(new OrderConfirmation($event->order));
    }
}
