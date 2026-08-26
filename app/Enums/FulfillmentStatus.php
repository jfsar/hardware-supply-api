<?php

namespace App\Enums;

/**
 * Coarse fulfillment progress on orders.fulfillment_status. Shipment-level
 * detail arrives with Phase 6 (FR-ORD-005).
 */
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
}
