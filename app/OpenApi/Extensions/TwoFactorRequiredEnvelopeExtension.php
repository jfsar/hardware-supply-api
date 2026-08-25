<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Auth\TwoFactorRequiredException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class TwoFactorRequiredEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(TwoFactorRequiredException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'TWO_FACTOR_REQUIRED';
    }

    protected function summary(): string
    {
        return 'Two-factor authentication required';
    }

    protected function details(Type $type): OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('challenge_token', (new OpenApiTypes\StringType)
                ->setDescription('Single-use token to submit with POST /auth/2fa/challenge.'));
    }
}
