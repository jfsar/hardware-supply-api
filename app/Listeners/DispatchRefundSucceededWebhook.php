<?php

namespace App\Listeners;

use App\Events\RefundSucceeded;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Fans the refund.completed outbound webhook (FR-NOTIF-003).
 */
class DispatchRefundSucceededWebhook
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(RefundSucceeded $event): void
    {
        $refund = $event->refund;

        $this->dispatcher->dispatch('refund.completed', [
            'refund_id' => $refund->ulid,
            'order_id' => $refund->order?->ulid,
            'order_number' => $refund->order?->order_number,
            'amount_minor' => $refund->amount_minor,
            'currency' => $refund->currency_code,
            'status' => $refund->status->value,
        ]);
    }
}
