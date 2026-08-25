<?php

namespace App\OpenApi\Extensions;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthorizationEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && (
                $type->isInstanceOf(AuthorizationException::class)
                || $type->isInstanceOf(AccessDeniedHttpException::class)
            );
    }

    protected function status(): int
    {
        return 403;
    }

    protected function errorCode(): string
    {
        return 'FORBIDDEN';
    }

    protected function summary(): string
    {
        return 'Forbidden';
    }
}
