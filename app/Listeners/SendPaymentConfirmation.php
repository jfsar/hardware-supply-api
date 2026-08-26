<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\User;
use App\Notifications\Orders\PaymentConfirmation;
use App\Services\Notifications\NotificationPreferenceGate;
use Illuminate\Support\Facades\Notification;

/**
 * Queues the payment confirmation email for registered customers,
 * honoring their notification preferences (Phase 5 Task 4). Guest
 * orders have no notifiable account; Phase 7 adds guest channels.
 */
class SendPaymentConfirmation
{
    public function __construct(protected NotificationPreferenceGate $preferenceGate) {}

    public function handle(PaymentReceived $event): void
    {
        /** @var User|null $user */
        $user = $event->order->user;

        if ($user === null) {
            return;
        }

        if (! ($this->preferenceGate)->allows($user, 'order_updates')) {
            return;
        }

        $user->notify(new PaymentConfirmation($event->order));
    }
}
