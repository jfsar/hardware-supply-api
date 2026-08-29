<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable order line snapshot: product identity, price, tax, discount,
 * and lifecycle quantity counters survive any later catalog change
 * (FR-ORD-002, NFR-DATA-003).
 */
#[Fillable([
    'order_id', 'product_variant_id', 'sku_snapshot', 'product_name_snapshot',
    'variant_name_snapshot', 'unit_price_minor', 'quantity', 'discount_minor',
    'tax_minor', 'line_total_minor', 'quantity_cancelled', 'quantity_fulfilled',
    'quantity_returned', 'quantity_refunded',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|float>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'quantity_cancelled' => 'float',
            'quantity_fulfilled' => 'float',
            'quantity_returned' => 'float',
            'quantity_refunded' => 'float',
        ];
    }

    /**
     * The owning order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The variant at purchase time; null once the variant is deleted —
     * the snapshots keep history intact.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Quantity still active after prior cancellations.
     */
    public function remainingQuantity(): float
    {
        return (float) $this->quantity - (float) $this->quantity_cancelled;
    }
}
