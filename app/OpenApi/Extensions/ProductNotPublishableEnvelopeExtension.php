<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Catalog\ProductNotPublishableException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ProductNotPublishableEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ProductNotPublishableException::class);
    }

    protected function status(): int
    {
        return 422;
    }

    protected function errorCode(): string
    {
        return 'PRODUCT_NOT_PUBLISHABLE';
    }

    protected function summary(): string
    {
        return 'Product not publishable';
    }
}
