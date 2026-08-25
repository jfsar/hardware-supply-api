<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Inventory\NegativeStockException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class NegativeStockEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(NegativeStockException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'STOCK_NEGATIVE_NOT_ALLOWED';
    }

    protected function summary(): string
    {
        return 'Adjustment would drive stock negative';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('sku', (new OpenApiTypes\StringType)
                ->setDescription('SKU whose adjustment was rejected.'));
    }
}
