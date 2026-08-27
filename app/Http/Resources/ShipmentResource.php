<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Shipment $resource
 */
class ShipmentResource extends JsonResource
{
    /**
     * Shipment payload with items and the append-only tracking timeline.
     * Estimated delivery stays distinct from actual timestamps; actuals
     * appear only once they occur (FR-SHIP-007).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'shipment_number' => $this->resource->shipment_number,
            'status' => $this->resource->status->value,
            'tracking_number' => $this->resource->tracking_number,
            'carrier_name' => $this->resource->carrier_name,
            'shipping_method' => $this->whenLoaded('method', fn (): ?string => $this->resource->method?->code),
            'shipping_method_label' => $this->whenLoaded('method', fn (): ?string => $this->resource->method?->name),
            'pickup_location_id' => $this->resource->pickup_location_id,
            'estimated_delivery_at' => optional($this->resource->estimated_delivery_at)->toISOString(),
            'shipped_at' => optional($this->resource->shipped_at)->toISOString(),
            'delivered_at' => optional($this->resource->delivered_at)->toISOString(),
            'picked_up_at' => optional($this->resource->picked_up_at)->toISOString(),
            'items' => $this->whenLoaded('items', fn (): array => $this->resource->items
                ->map(fn ($item): array => [
                    'order_item_id' => $item->order_item_id,
                    'quantity' => (float) $item->quantity,
                ])->all()),
            'tracking_events' => $this->whenLoaded('trackingEvents', fn (): array => $this->resource->trackingEvents
                ->map(fn ($event): array => [
                    'status' => $event->status,
                    'location_text' => $event->location_text,
                    'event_at' => optional($event->event_at)->toISOString(),
                    'description' => $event->description,
                ])->all()),
            'created_at' => optional($this->resource->created_at)->toISOString(),
        ];
    }
}
