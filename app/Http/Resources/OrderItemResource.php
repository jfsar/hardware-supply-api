<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property OrderItem $resource
 */
class OrderItemResource extends JsonResource
{
    /**
     * Immutable line snapshot fields only — nothing derived from the
     * live catalog (NFR-DATA-003).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'sku' => $this->resource->sku_snapshot,
            'product_name' => $this->resource->product_name_snapshot,
            'variant_name' => $this->resource->variant_name_snapshot,
            'quantity' => (float) $this->resource->quantity,
            'quantity_cancelled' => (float) $this->resource->quantity_cancelled,
            'unit_price_minor' => (int) $this->resource->unit_price_minor,
            'discount_minor' => (int) $this->resource->discount_minor,
            'tax_minor' => (int) $this->resource->tax_minor,
            'line_total_minor' => (int) $this->resource->line_total_minor,
        ];
    }
}
