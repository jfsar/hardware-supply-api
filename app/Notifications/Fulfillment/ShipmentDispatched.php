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
 * Dispatch email (Phase 6 Task 6): sent on the notifications queue when
 * a delivery shipment ships or a pickup shipment is ready for collection.
 */
class ShipmentDispatched extends Notification implements ShouldQueue
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
     * Build the dispatch mail; the notifiable is the customer.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $shipment = $this->shipment->loadMissing('order');

        $message = (new MailMessage)
            ->subject(__('Your order :number is on the way', ['number' => $shipment->order->order_number]))
            ->greeting(__('Great news!'))
            ->line(__('Shipment **:shipment** for order **:order** is on its way.', [
                'shipment' => $shipment->shipment_number,
                'order' => $shipment->order->order_number,
            ]));

        if ($shipment->tracking_number !== null) {
            $message->line(__('Tracking number: **:number**', ['number' => $shipment->tracking_number]));
        }

        if ($shipment->estimated_delivery_at !== null) {
            $message->line(__('Estimated delivery: **:date**', [
                'date' => $shipment->estimated_delivery_at->format('M j, Y'),
            ]));
        }

        return $message->line(__('We will let you know as soon as it arrives.'));
    }
}
