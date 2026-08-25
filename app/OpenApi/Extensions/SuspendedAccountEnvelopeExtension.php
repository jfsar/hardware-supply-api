<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Auth\SuspendedAccountException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class SuspendedAccountEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(SuspendedAccountException::class);
    }

    protected function status(): int
    {
        return 403;
    }

    protected function errorCode(): string
    {
        return 'ACCOUNT_SUSPENDED';
    }

    protected function summary(): string
    {
        return 'Account suspended';
    }
}
