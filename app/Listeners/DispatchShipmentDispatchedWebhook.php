<?php

namespace App\Listeners;

use App\Events\ShipmentDispatched;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Fans the order.shipped outbound webhook (FR-NOTIF-003).
 */
class DispatchShipmentDispatchedWebhook
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(ShipmentDispatched $event): void
    {
        $shipment = $event->shipment;

        $this->dispatcher->dispatch('order.shipped', [
            'shipment_id' => $shipment->ulid,
            'shipment_number' => $shipment->shipment_number,
            'order_id' => $shipment->order?->ulid,
            'order_number' => $shipment->order?->order_number,
            'status' => $shipment->status->value,
            'carrier_name' => $shipment->carrier_name,
            'tracking_number' => $shipment->tracking_number,
        ]);
    }
}
