<?php

namespace App\Http\Resources\Inventory;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Inventory $resource
 */
class AdminInventoryResource extends JsonResource
{
    /**
     * Staff stock row; raw counts are safe behind inventory.view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'variant_ulid' => $this->variant?->ulid,
            'sku' => $this->variant?->sku,
            'product' => $this->whenLoaded('variant', fn (): ?string => $this->variant?->product?->name),
            'location' => [
                'code' => $this->location?->code,
                'name' => $this->location?->name,
            ],
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'available_quantity' => $this->availableQuantity(),
            'reorder_level' => (float) $this->reorder_level,
            'is_low_stock' => $this->isLowStock(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
