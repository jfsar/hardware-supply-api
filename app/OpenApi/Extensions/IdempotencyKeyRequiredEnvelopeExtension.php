<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Http\IdempotencyKeyRequiredException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class IdempotencyKeyRequiredEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(IdempotencyKeyRequiredException::class);
    }

    protected function status(): int
    {
        return 422;
    }

    protected function errorCode(): string
    {
        return 'IDEMPOTENCY_KEY_REQUIRED';
    }

    protected function summary(): string
    {
        return 'An Idempotency-Key header is required on this endpoint';
    }
}
