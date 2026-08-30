<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual price adjustment applied to an order (Phase 8, SRS §69).
 * Append-only rows tagged with a signed amount drive the order's
 * adjustment_minor aggregate and, through it, the reconciled total.
 */
#[Fillable([
    'order_id', 'type', 'label', 'amount_minor', 'currency_code', 'reason', 'created_by_user_id',
])]
class OrderAdjustment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    /**
     * The adjusted order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The admin who applied the adjustment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
