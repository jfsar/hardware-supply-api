<?php

namespace App\Enums;

/**
 * How an applied discount computes its amount (SRS §16).
 * FreeShipping flags the shipping line instead of subtracting money.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case BuyXGetY = 'buy_x_get_y';
    case QuantityDiscount = 'quantity_discount';
    case FreeShipping = 'free_shipping';

    /**
     * Whether the discount reduces monetary line totals.
     */
    public function isMonetary(): bool
    {
        return $this !== self::FreeShipping;
    }
}
