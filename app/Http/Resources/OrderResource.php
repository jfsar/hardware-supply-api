<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class OrderResource extends JsonResource
{
    /**
     * Public order payload with immutable snapshots (FR-ORD-002/010).
     * Status histories only render when the relation was loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'order_number' => $this->resource->order_number,
            'order_status' => $this->resource->order_status->value,
            'payment_status' => $this->resource->payment_status->value,
            'fulfillment_status' => $this->resource->fulfillment_status->value,
            'currency_code' => $this->resource->currency_code,
            'subtotal_minor' => (int) $this->resource->subtotal_minor,
            'discount_minor' => (int) $this->resource->discount_minor,
            'shipping_minor' => (int) $this->resource->shipping_minor,
            'tax_minor' => (int) $this->resource->tax_minor,
            'adjustment_minor' => (int) $this->resource->adjustment_minor,
            'total_minor' => (int) $this->resource->total_minor,
            'customer_email' => $this->resource->customer_email,
            'customer_phone' => $this->resource->customer_phone,
            'placed_at' => optional($this->resource->placed_at)->toISOString(),
            'cancelled_at' => optional($this->resource->cancelled_at)->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'addresses' => $this->whenLoaded('addresses', fn (): array => $this->resource->addresses
                ->map(fn ($address): array => [
                    'address_type' => $address->address_type,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'notes' => $address->notes,
                ])->all()),
            'status_histories' => $this->whenLoaded('statusHistories', fn (): array => $this->resource->statusHistories
                ->map(fn ($history): array => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'reason' => $history->reason,
                    'changed_by_user_id' => $history->changed_by_user_id,
                    'created_at' => optional($history->created_at)->toISOString(),
                ])->all()),
        ];
    }
}
