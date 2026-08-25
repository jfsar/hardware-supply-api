<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';

    /**
     * Whether the reservation is still holding stock.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Whether the reservation reached a final state and no longer holds stock.
     */
    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
