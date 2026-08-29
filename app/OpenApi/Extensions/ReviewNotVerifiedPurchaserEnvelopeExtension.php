<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Reviews\ReviewNotVerifiedPurchaserException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ReviewNotVerifiedPurchaserEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ReviewNotVerifiedPurchaserException::class);
    }

    protected function status(): int
    {
        return 403;
    }

    protected function errorCode(): string
    {
        return 'REVIEW_NOT_VERIFIED_PURCHASER';
    }

    protected function summary(): string
    {
        return 'The customer has no delivered purchase for the product';
    }
}
