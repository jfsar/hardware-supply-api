<?php

namespace App\Enums;

/**
 * Ledger entry type on payment_transactions (SRS §19).
 */
enum TransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
}
