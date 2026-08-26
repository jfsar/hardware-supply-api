<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Pricing\CouponException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * One extension for all coupon failures; error.code is one of
 * COUPON_INVALID, COUPON_EXPIRED, COUPON_LIMIT_REACHED.
 */
final class CouponEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(CouponException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'COUPON_INVALID';
    }

    protected function summary(): string
    {
        return 'Coupon rejected: COUPON_INVALID, COUPON_EXPIRED, or COUPON_LIMIT_REACHED';
    }
}
