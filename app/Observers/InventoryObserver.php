<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\ProductVariant;

class InventoryObserver
{
    /**
     * Provision a zero-quantity stock row at the primary warehouse so every
     * variant always has an inventory record to adjust or reserve against.
     */
    public function created(ProductVariant $variant): void
    {
        $location = Location::primaryWarehouse();

        if ($location === null) {
            return;
        }

        Inventory::query()->firstOrCreate([
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
        ]);
    }
}
