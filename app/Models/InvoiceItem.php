<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single frozen line on an invoice, snapshot from the order item it
 * references (Phase 8, FR-ORD-008).
 */
#[Fillable([
    'invoice_id', 'order_item_id', 'description', 'quantity',
    'unit_price_minor', 'tax_minor', 'line_total_minor',
])]
class InvoiceItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price_minor' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    /**
     * The owning invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The original order line this row snapshots (kept when available).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
