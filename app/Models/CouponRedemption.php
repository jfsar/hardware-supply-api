<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable redemption evidence written inside the checkout transaction;
 * usage counters derive from these rows under a locked coupon read.
 */
#[Fillable(['coupon_id', 'user_id', 'order_id', 'discount_amount_minor', 'currency_code', 'redeemed_at'])]
class CouponRedemption extends Model
{
    /**
     * The redeemed coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The redeeming customer, null for guest orders.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The order that consumed the coupon.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
