<?php

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * Whether the session can still be converted into an order.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
