<?php

namespace App\OpenApi\Extensions;

use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;

final class ValidationEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ValidationException::class);
    }

    protected function status(): int
    {
        return 422;
    }

    protected function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    protected function summary(): string
    {
        return 'Validation failed';
    }

    protected function details(Type $type): OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('fields', (new OpenApiTypes\ObjectType)
                ->setDescription('Per-field validation messages keyed by field name.')
                ->additionalProperties((new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType)));
    }
}
