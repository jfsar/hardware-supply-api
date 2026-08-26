<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Http\IdempotencyConflictException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class IdempotencyConflictEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(IdempotencyConflictException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'IDEMPOTENCY_CONFLICT';
    }

    protected function summary(): string
    {
        return 'The idempotency key was already used with a different payload';
    }
}
