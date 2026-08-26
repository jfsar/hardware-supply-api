<?php

namespace App\Services\Pricing\Promotions;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Redemption counts backing promotion/coupon usage limits. Centralized
 * so both the eligibility checker and the coupon validator agree on the
 * same evidence rows.
 */
class CouponRedemptionCount
{
    /**
     * Redemptions recorded against any coupon linked to the promotion,
     * optionally narrowed to one customer.
     */
    public static function forPromotion(Promotion $promotion, ?User $user = null): int
    {
        return DB::table('coupon_redemptions')
            ->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')
            ->where('coupons.promotion_id', $promotion->getKey())
            ->when($user !== null, fn ($query) => $query->where('coupon_redemptions.user_id', $user->getKey()))
            ->count();
    }
}
