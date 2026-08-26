<?php

namespace App\Actions\Cart;

use App\Models\Cart;

/**
 * Detaches applied coupons from the cart.
 */
class RemoveCoupon
{
    public function __invoke(Cart $cart): void
    {
        $cart->couponRows()->delete();
    }
}
