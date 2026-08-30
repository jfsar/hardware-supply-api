<?php

namespace App\Enums;

/**
 * Lifecycle of an issued credit note (Phase 8). Raised against an invoice
 * when a refund settles; correctable only by flipping to Void.
 */
enum CreditNoteStatus: string
{
    case Issued = 'issued';
    case Void = 'void';

    /**
     * Whether this document currently represents refundable value.
     */
    public function isActive(): bool
    {
        return $this === self::Issued;
    }
}
