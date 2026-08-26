<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Orders\OrderStateException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class OrderStateInvalidEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(OrderStateException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'ORDER_STATE_INVALID';
    }

    protected function summary(): string
    {
        return 'The requested order action is illegal in its current state';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('current_status', new OpenApiTypes\StringType)
            ->addProperty('target_status', new OpenApiTypes\StringType)
            ->addRequired(['current_status', 'target_status']);
    }
}
