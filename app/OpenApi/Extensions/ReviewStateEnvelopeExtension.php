<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Reviews\ReviewStateException;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ReviewStateEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ReviewStateException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'REVIEW_STATE_INVALID';
    }

    protected function summary(): string
    {
        return 'The requested review moderation action is illegal in its current state';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('current_status', new OpenApiTypes\StringType)
            ->addProperty('target_status', new OpenApiTypes\StringType)
            ->addRequired(['current_status', 'target_status']);
    }
}
