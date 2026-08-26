<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Cart\VariantNotPurchasableException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class VariantNotPurchasableEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(VariantNotPurchasableException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'VARIANT_NOT_PURCHASABLE';
    }

    protected function summary(): string
    {
        return 'The variant is no longer purchasable';
    }
}
