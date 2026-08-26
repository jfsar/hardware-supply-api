<?php

namespace App\Enums;

/**
 * Which pricing layer produced a resolved unit price (Phase 4 Task 3).
 */
enum PricingSource: string
{
    case CustomerPriceList = 'customer_price_list';
    case PriceList = 'price_list';
    case QuantityTier = 'quantity_tier';
}
