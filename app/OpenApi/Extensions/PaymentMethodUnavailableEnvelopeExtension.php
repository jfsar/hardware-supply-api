<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Checkout\PaymentMethodUnavailableException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class PaymentMethodUnavailableEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(PaymentMethodUnavailableException::class);
    }

    protected function status(): int
    {
        return 422;
    }

    protected function errorCode(): string
    {
        return 'PAYMENT_METHOD_UNAVAILABLE';
    }

    protected function summary(): string
    {
        return 'Gateway-driven methods arrive with Phase 5; use cash on delivery';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('method', new OpenApiTypes\StringType)
            ->addRequired(['method']);
    }
}
