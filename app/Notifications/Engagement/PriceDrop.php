<?php

namespace App\Notifications\Engagement;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Price-drop alert (FR-NOTIF-002): messenger for a drop that crossed the
 * subscriber's target (when set) or any drop below the last price.
 */
class PriceDrop extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $productVariantId  the repriced variant's id
     */
    public function __construct(
        public readonly int $productVariantId,
        public readonly ?string $productName,
        public readonly ?string $variantName,
        public readonly int $previousMinor,
        public readonly int $newMinor,
        public readonly string $currencyCode,
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
     * Build the price-drop mail; the notifiable is a user or anonymous email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $name = trim(($this->productName ?? '').' '.($this->variantName ?? ''));
        $product = $name !== '' ? $name : __('Item');

        return (new MailMessage)
            ->subject(__(':product is now on sale', ['product' => $product]))
            ->greeting(__('Price alert!'))
            ->line(__(':product dropped in price.', ['product' => $product]))
            ->line(__('From :previous to :new.', [
                'previous' => Money::format($this->previousMinor, $this->currencyCode),
                'new' => Money::format($this->newMinor, $this->currencyCode),
            ]));
    }
}
