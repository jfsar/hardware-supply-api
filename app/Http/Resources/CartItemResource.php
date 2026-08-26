<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CartItem $resource
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the cart line for public consumption. Pricing fields are
     * filled by the totals pipeline owner when available.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = $this->resource->getAttribute('resolved_pricing');

        return [
            'id' => (int) $this->resource->getKey(),
            'quantity' => (float) $this->resource->quantity,
            'variant' => $this->resource->variant !== null ? [
                'ulid' => $this->resource->variant->ulid,
                'sku' => $this->resource->variant->sku,
                'name' => $this->resource->variant->name,
            ] : null,
            'unit_price_minor' => $pricing['unit_price_minor'] ?? null,
            'line_total_minor' => $pricing['line_total_minor'] ?? null,
        ];
    }
}
