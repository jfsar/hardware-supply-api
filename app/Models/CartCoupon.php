<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row for coupons applied to a cart; created_at only per schema.
 */
#[Fillable(['cart_id', 'coupon_id', 'applied_by_user_id'])]
class CartCoupon extends Model
{
    public const UPDATED_AT = null;

    /**
     * The cart this coupon is applied to.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The applied coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
