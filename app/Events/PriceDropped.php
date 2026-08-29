<?php

namespace App\Events;

use App\Models\ProductVariant;

class PriceDropped
{
    /**
     * Fired when a price record lands below the previously effective price
     * for the same variant/list pair (FR-PRICE-005).
     */
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $previousMinor,
        public readonly int $newMinor,
        public readonly string $currencyCode,
    ) {}
}
