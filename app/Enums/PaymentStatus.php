<?php

namespace App\Enums;

/**
 * Payment lifecycle shared by payments.status (SRS §19).
 * Gateway transitions arrive with Phase 5; Phase 4 writes Pending rows.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /**
     * Whether the payment settled in full.
     */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded], true);
    }

    /**
     * Whether no further state change is expected without operator action.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled, self::Expired, self::Refunded], true);
    }
}
