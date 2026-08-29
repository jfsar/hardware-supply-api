<?php

namespace App\Events;

use App\Models\Product;
use App\Models\User;

class ProductViewed
{
    /**
     * Fired whenever a public product page is rendered so the recently
     * viewed window can be recorded per customer or guest session (FR-DISC-002).
     */
    public function __construct(
        public readonly Product $product,
        public readonly ?User $user,
        public readonly ?string $sessionHash,
    ) {}
}
