<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\PaymentReceived;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Fans the payment.succeeded outbound webhook (FR-NOTIF-003).
 */
class DispatchPaymentReceivedWebhook
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(PaymentReceived $event): void
    {
        $order = $event->order;

        $payment = $order->payments()
            ->where('status', PaymentStatus::Paid)
            ->latest('id')
            ->first();

        $this->dispatcher->dispatch('payment.succeeded', [
            'order_id' => $order->ulid,
            'order_number' => $order->order_number,
            'payment_id' => $payment?->ulid,
            'amount_minor' => $payment?->amount_minor ?? $order->total_minor,
            'currency' => $order->currency_code,
        ]);
    }
}
