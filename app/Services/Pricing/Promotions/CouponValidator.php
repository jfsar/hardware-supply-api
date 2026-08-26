<?php

namespace App\Services\Pricing\Promotions;

use App\Exceptions\Pricing\CouponException;
use App\Models\Coupon;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Coupon code validation (SRS §16 / Phase 4 Task 5): resolves the code,
 * checks activity and validity window, enforces global and per-customer
 * usage limits, and reports typed failures rendered as 409s.
 */
class CouponValidator
{
    /**
     * Validate a coupon code, returning the coupon on success.
     *
     * @throws CouponException with a stable COUPON_* error code
     */
    public function __invoke(string $code, ?User $user = null, ?CarbonInterface $at = null): Coupon
    {
        $at ??= Carbon::now();

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();

        if ($coupon === null || ! $coupon->is_active) {
            throw CouponException::invalid();
        }

        if ($coupon->starts_at->gt($at)) {
            throw CouponException::invalid();
        }

        if ($coupon->ends_at->lte($at)) {
            throw CouponException::expired();
        }

        $redeemed = $coupon->redemptions()->count();

        if ($coupon->usage_limit !== null && $redeemed >= $coupon->usage_limit) {
            throw CouponException::limitReached();
        }

        if ($user !== null && $coupon->per_customer_limit !== null) {
            $perCustomer = $coupon->redemptions()
                ->where('user_id', $user->getKey())
                ->count();

            if ($perCustomer >= $coupon->per_customer_limit) {
                throw CouponException::limitReached();
            }
        }

        return $coupon;
    }
}
