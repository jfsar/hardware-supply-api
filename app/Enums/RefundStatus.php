<?php

namespace App\Enums;

/**
 * Refund lifecycle shared by refunds.status (SRS §55). Rows start Pending
 * in the outbox; ProcessRefund finalizes them from provider truth.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Whether funds actually left the captured balance.
     */
    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }
}
