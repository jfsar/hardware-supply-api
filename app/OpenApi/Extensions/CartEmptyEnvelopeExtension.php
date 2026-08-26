<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Checkout\CartEmptyException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class CartEmptyEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(CartEmptyException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'CART_EMPTY';
    }

    protected function summary(): string
    {
        return 'The cart has no lines to check out';
    }
}
