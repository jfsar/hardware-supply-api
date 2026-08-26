<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Empties all lines and detached coupon state from a cart.
 */
class ClearCart
{
    public function __invoke(Cart $cart): void
    {
        DB::transaction(function () use ($cart): void {
            $cart->items()->delete();
            $cart->couponRows()->delete();
        });
    }
}
