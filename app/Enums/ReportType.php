<?php

namespace App\Enums;

/**
 * Synchronous + exportable report kinds (Phase 8, FR-RPT-001). Each case
 * maps to the invokable query service under App\Services\Reports that
 * aggregates immutable snapshot columns over settled financial state.
 */
enum ReportType: string
{
    case Sales = 'sales';
    case Orders = 'orders';
    case Inventory = 'inventory';
    case LowStock = 'low_stock';
    case Customers = 'customers';
    case Payments = 'payments';
    case Refunds = 'refunds';
    case Promotions = 'promotions';
    case Tax = 'tax';
    case Profit = 'profit';

    /**
     * Whether this report returns a day-bucket time series rather than a
     * row-per-entity list (controls sync pagination vs. full series).
     */
    public function isSeries(): bool
    {
        return in_array($this, [self::Sales, self::Tax], true);
    }
}
