<?php

namespace App\Enums;

/**
 * Lifecycle of an issued invoice record (Phase 8, FR-ORD-008). Corrective
 * documents flip an invoice to Void; totals are never mutated in place so
 * the billed snapshot stays trustworthy.
 */
enum InvoiceStatus: string
{
    case Issued = 'issued';
    case Void = 'void';

    /**
     * Whether this document currently represents billable value.
     */
    public function isActive(): bool
    {
        return $this === self::Issued;
    }
}
