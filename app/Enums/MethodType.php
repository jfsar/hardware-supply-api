<?php

namespace App\Enums;

/**
 * Shipping method categories (SRS §21). Determines how an order
 * reaches the customer — own fleet, third-party courier, or store pickup.
 */
enum MethodType: string
{
    case OwnDelivery = 'own_delivery';
    case Courier = 'courier';
    case Pickup = 'pickup';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::OwnDelivery => 'Own Delivery',
            self::Courier => 'Courier',
            self::Pickup => 'Store Pickup',
        };
    }

    /**
     * Whether this method delivers to a customer address (vs. customer collection).
     */
    public function isDelivery(): bool
    {
        return $this !== self::Pickup;
    }
}
