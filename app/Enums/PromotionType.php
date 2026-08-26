<?php

namespace App\Enums;

/**
 * Promotion mechanics (SRS §16). The type gates eligibility semantics;
 * the amount math itself follows DiscountType.
 */
enum PromotionType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case BuyXGetY = 'buy_x_get_y';
    case QuantityDiscount = 'quantity_discount';
    case FlashSale = 'flash_sale';
    case FreeShipping = 'free_shipping';
}
