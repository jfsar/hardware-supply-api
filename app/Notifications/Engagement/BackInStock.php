<?php

namespace App\Notifications\Engagement;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Back-in-stock alert (FR-NOTIF-002), sent to the subscribed email
 * (customer or guest) and always queued on the notifications queue.
 */
class BackInStock extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $productVariantId  the restocked variant's id
     */
    public function __construct(
        public readonly int $productVariantId,
        public readonly ?string $productName,
        public readonly ?string $variantName,
    ) {
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
     * Build the restock mail; the notifiable is a user or anonymous email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $name = trim(($this->productName ?? '').' '.($this->variantName ?? ''));
        $product = $name !== '' ? $name : __('Item');

        return (new MailMessage)
            ->subject(__(':product is back in stock', ['product' => $product]))
            ->greeting(__('Good news!'))
            ->line(__(':product is available again.', ['product' => $product]))
            ->line(__('Grab it while it lasts.'));
    }
}
