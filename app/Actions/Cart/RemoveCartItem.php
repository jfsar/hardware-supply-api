<?php

namespace App\Actions\Cart;

use App\Models\CartItem;

/**
 * Removes a single line from the cart.
 */
class RemoveCartItem
{
    public function __invoke(CartItem $item): void
    {
        $item->delete();
    }
}
