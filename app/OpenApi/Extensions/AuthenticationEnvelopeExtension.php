<?php

namespace App\OpenApi\Extensions;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;

final class AuthenticationEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(AuthenticationException::class);
    }

    protected function status(): int
    {
        return 401;
    }

    protected function errorCode(): string
    {
        return 'UNAUTHENTICATED';
    }

    protected function summary(): string
    {
        return 'Unauthenticated';
    }
}
