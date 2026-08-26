<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Payment confirmation email (Phase 5 Task 4), queued on the
 * notifications queue alongside the order confirmation.
 */
class PaymentConfirmation extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Queue on the dedicated notifications worker queue.
     */
    public function __construct(public readonly Order $order)
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
     * Build the confirmation mail; the notifiable is the customer.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('We received your payment for :number', ['number' => $this->order->order_number]))
            ->greeting(__('Payment received!'))
            ->line(__('Your payment for order **:number** was received successfully.', ['number' => $this->order->order_number]))
            ->line(__('Total: :total', [
                'total' => Money::format((int) $this->order->total_minor, $this->order->currency_code),
            ]))
            ->line(__('We are preparing your items for fulfillment.'));
    }
}
