<?php

namespace App\Services\Inventory\Concerns;

use App\Enums\MovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarantees every quantity change is paired with exactly one immutable
 * ledger row (NFR-DATA-004). Callers must already be inside a transaction
 * that holds a lock on the inventory row.
 *
 * quantity_before/quantity_after track quantity_on_hand for stock movements
 * (purchase, sale, return, adjustment, damage, loss, transfer) and derived
 * availability for reservation movements, which never touch on-hand stock.
 */
trait RecordsMovements
{
    /**
     * Persist one ledger row describing the change just applied.
     */
    protected function recordMovement(
        Inventory $inventory,
        MovementType $type,
        float $delta,
        float $before,
        float $after,
        ?string $reason = null,
        ?Model $reference = null,
        ?User $actor = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'location_id' => $inventory->location_id,
            'product_variant_id' => $inventory->product_variant_id,
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $reference === null ? null : class_basename($reference),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'performed_by_user_id' => $actor?->id,
        ]);
    }
}
