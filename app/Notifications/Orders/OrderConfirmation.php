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
 * Order confirmation email (Phase 4 Task 10), sent through Mailpit in
 * development and always queued on the notifications queue.
 */
class OrderConfirmation extends Notification implements ShouldQueue
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
            ->subject(__('Your order :number is confirmed', ['number' => $this->order->order_number]))
            ->greeting(__('Thanks for your order!'))
            ->line(__('Order **:number** has been placed successfully.', ['number' => $this->order->order_number]))
            ->line(__('Total: :total', [
                'total' => Money::format((int) $this->order->total_minor, $this->order->currency_code),
            ]))
            ->line(__('We will notify you as soon as it ships.'));
    }
}
