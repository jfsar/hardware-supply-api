<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

/**
 * Recomputed checkout totals drifted from the signed checkout_token
 * (prices/promotions/shipping/tax changed between validate and place).
 */
class CheckoutTotalsChangedException extends RuntimeException
{
    public static function staleToken(): self
    {
        return new self(__('Checkout pricing has changed since validation. Please revalidate your order.'));
    }
}
