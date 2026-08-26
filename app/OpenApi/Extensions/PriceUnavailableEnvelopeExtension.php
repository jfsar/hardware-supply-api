<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Pricing\PriceUnavailableException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class PriceUnavailableEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(PriceUnavailableException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'PRICE_UNAVAILABLE';
    }

    protected function summary(): string
    {
        return 'No active price could be resolved for the item';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('sku', new OpenApiTypes\StringType)
            ->addRequired(['sku']);
    }
}
