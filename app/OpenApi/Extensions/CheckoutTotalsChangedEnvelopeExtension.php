<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Checkout\CheckoutTotalsChangedException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class CheckoutTotalsChangedEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(CheckoutTotalsChangedException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'CHECKOUT_TOTALS_CHANGED';
    }

    protected function summary(): string
    {
        return 'Totals drifted from the signed checkout token; revalidate first';
    }
}
