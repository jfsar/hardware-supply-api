<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-customer notification opt-outs (one row per user); absent rows are
 * treated as opted-in everywhere via NotificationPreferenceGate.
 */
#[Fillable([
    'user_id', 'order_updates_enabled', 'payment_updates_enabled', 'promotions_enabled',
    'back_in_stock_enabled', 'price_drop_enabled',
])]
class NotificationPreference extends Model
{
    /**
     * The gated customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
