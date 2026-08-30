<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Fans the order.created outbound webhook (FR-NOTIF-003).
 */
class DispatchOrderCreatedWebhook
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        $this->dispatcher->dispatch('order.created', [
            'order_id' => $order->ulid,
            'order_number' => $order->order_number,
            'status' => $order->order_status->value,
            'currency' => $order->currency_code,
            'total_minor' => $order->total_minor,
        ]);
    }
}
