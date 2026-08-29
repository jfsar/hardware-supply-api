<?php

namespace App\Events;

use App\Models\ProductVariant;

class InventoryBecameAvailable
{
    /**
     * Fired when a variant transitions from no sellable stock to stock
     * available: on-hand grew while locked, or a reservation released it.
     */
    public function __construct(public readonly ProductVariant $variant) {}
}
