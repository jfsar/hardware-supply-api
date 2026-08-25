<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Inventory\InsufficientStockException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class InsufficientStockEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(InsufficientStockException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'STOCK_INSUFFICIENT';
    }

    protected function summary(): string
    {
        return 'Insufficient stock for one or more items';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        $skus = (new OpenApiTypes\StringType)
            ->setDescription('SKU whose requested quantity exceeded availability.');

        return (new OpenApiTypes\ObjectType)
            ->addProperty('skus', new OpenApiTypes\ArrayType($skus))
            ->addRequired('skus');
    }
}
