<?php

namespace App\Enums;

enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case Loss = 'loss';
    case Transfer = 'transfer';
    case Reservation = 'reservation';
    case ReservationRelease = 'reservation_release';

    /**
     * Whether the movement adds stock at a location. Transfer and Adjustment
     * carry their direction in the signed delta, so they are neither.
     */
    public function isInbound(): bool
    {
        return match ($this) {
            self::Purchase, self::Return, self::ReservationRelease => true,
            default => false,
        };
    }

    /**
     * Whether the movement removes stock from a location.
     */
    public function isOutbound(): bool
    {
        return match ($this) {
            self::Sale, self::Damage, self::Loss, self::Reservation => true,
            default => false,
        };
    }
}
