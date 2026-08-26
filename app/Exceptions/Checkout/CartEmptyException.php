<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

class CartEmptyException extends RuntimeException
{
    public static function empty(): self
    {
        return new self(__('Your cart is empty.'));
    }
}
