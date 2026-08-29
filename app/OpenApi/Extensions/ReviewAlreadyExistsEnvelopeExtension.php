<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Reviews\ReviewAlreadyExistsException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ReviewAlreadyExistsEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ReviewAlreadyExistsException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'REVIEW_ALREADY_EXISTS';
    }

    protected function summary(): string
    {
        return 'The customer already has an active review for the product';
    }
}
