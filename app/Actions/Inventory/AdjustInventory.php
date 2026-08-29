<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Events\InventoryBecameAvailable;
use App\Exceptions\Inventory\NegativeStockException;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\Concerns\RecordsMovements;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class AdjustInventory
{
    use RecordsMovements;

    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Apply a signed stock adjustment for one variant at one location,
     * rejecting any result that would drive on-hand stock negative.
     *
     * @throws NegativeStockException when the adjustment would go below zero
     */
    public function __invoke(
        User $actor,
        ProductVariant $variant,
        float $quantityDelta,
        MovementType $type,
        string $reason,
        ?Location $location = null,
    ): Inventory {
        [$inventory, $before, $after] = DB::transaction(function () use ($actor, $variant, $quantityDelta, $type, $reason, $location): array {
            $locationId = $location?->id ?? Location::primaryWarehouse()?->id;
            $inventory = Inventory::query()
                ->where('product_variant_id', $variant->id)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                $inventory = Inventory::query()->create([
                    'product_variant_id' => $variant->id,
                    'location_id' => $locationId,
                ]);
            }

            $before = (float) $inventory->quantity_on_hand;
            $after = $before + $quantityDelta;

            if ($after < 0) {
                throw NegativeStockException::forSku($variant->sku);
            }

            $inventory->quantity_on_hand = $after;
            $inventory->save();

            $this->recordMovement(
                $inventory,
                $type,
                $quantityDelta,
                $before,
                $after,
                $reason,
                $variant,
                $actor,
            );

            return [$inventory, $before, $after];
        });

        $beforeAvailable = $before - (float) $inventory->quantity_reserved;
        $afterAvailable = $after - (float) $inventory->quantity_reserved;

        ($this->recordAuditLog)($actor, 'inventory.adjusted', 'Inventory', (int) $inventory->getKey(), [
            'sku' => $variant->sku,
            'quantity_on_hand' => $before,
        ], [
            'sku' => $variant->sku,
            'movement_type' => $type->value,
            'quantity_delta' => $quantityDelta,
            'quantity_on_hand' => $after,
            'reason' => $reason,
        ]);

        if ($beforeAvailable <= 0.0 && $afterAvailable > 0.0) {
            event(new InventoryBecameAvailable($variant));
        }

        return $inventory->refresh();
    }
}
