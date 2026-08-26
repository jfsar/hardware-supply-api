<?php

namespace App\Exceptions\Pricing;

use RuntimeException;

/**
 * Coupon validation failures rendered as 409s with typed stable codes:
 * COUPON_INVALID, COUPON_EXPIRED, COUPON_LIMIT_REACHED.
 */
class CouponException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('COUPON_INVALID', __('The coupon code is not valid.'));
    }

    public static function expired(): self
    {
        return new self('COUPON_EXPIRED', __('This coupon has expired.'));
    }

    public static function limitReached(): self
    {
        return new self('COUPON_LIMIT_REACHED', __('This coupon has reached its usage limit.'));
    }
}
