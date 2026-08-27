<?php

namespace App\Notifications\Fulfillment;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Delivered email (Phase 6 Task 6): sent on the notifications queue when
 * a delivery shipment is delivered or a pickup shipment is collected.
 */
class ShipmentDelivered extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Queue on the dedicated notifications worker queue.
     */
    public function __construct(public readonly Shipment $shipment)
    {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the delivered mail; the notifiable is the customer.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $shipment = $this->shipment->loadMissing('order');

        return (new MailMessage)
            ->subject(__('Your order :number has arrived', ['number' => $shipment->order->order_number]))
            ->greeting(__('Delivered!'))
            ->line(__('Shipment **:shipment** for order **:order** has been delivered.', [
                'shipment' => $shipment->shipment_number,
                'order' => $shipment->order->order_number,
            ]))
            ->line(__('Thank you for shopping with us!'));
    }
}
