<?php

namespace App\Http\Resources\Inventory;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property InventoryMovement $resource
 */
class AdminInventoryMovementResource extends JsonResource
{
    /**
     * One ledger entry for the admin movement history.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'variant_ulid' => $this->variant?->ulid,
            'sku' => $this->variant?->sku,
            'movement_type' => $this->movement_type->value,
            'quantity_delta' => (float) $this->quantity_delta,
            'quantity_before' => (float) $this->quantity_before,
            'quantity_after' => (float) $this->quantity_after,
            'reference_type' => $this->reference_type,
            'reason' => $this->reason,
            'performed_by' => $this->whenLoaded('performedBy', fn (): ?string => $this->performedBy?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
