<?php

namespace App\Actions\Cart;

use App\Exceptions\Pricing\CouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use App\Services\Pricing\Promotions\CouponValidator;

/**
 * Attaches a validated coupon to the cart; final discount math and
 * redemption stay deferred to checkout (Phase 4 Task 2/5).
 */
class ApplyCoupon
{
    public function __construct(protected CouponValidator $couponValidator) {}

    /**
     * @throws CouponException on invalid/expired/exhausted coupons
     */
    public function __invoke(Cart $cart, string $code, ?User $user = null): Coupon
    {
        $coupon = ($this->couponValidator)($code, $user);

        if ($coupon->promotion === null) {
            throw CouponException::invalid();
        }

        $cart->couponRows()->firstOrCreate([
            'coupon_id' => $coupon->getKey(),
        ], [
            'applied_by_user_id' => $user?->getKey(),
        ]);

        return $coupon->load('promotion');
    }
}
