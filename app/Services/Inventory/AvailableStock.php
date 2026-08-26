<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;

/**
 * Total sellable units of a variant across all locations. Used by cart
 * mutations to cap quantities at real availability (Phase 4 Task 2);
 * checkout itself re-verifies through ReserveStock inside its transaction.
 */
class AvailableStock
{
    /**
     * Sum of derived availability (on hand − reserved) for the variant.
     */
    public function __invoke(ProductVariant $variant): float
    {
        return (float) $variant->inventories()
            ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as available')
            ->value('available');
    }
}
