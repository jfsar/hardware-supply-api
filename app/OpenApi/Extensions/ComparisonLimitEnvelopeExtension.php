<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Engagement\ComparisonLimitReachedException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class ComparisonLimitEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ComparisonLimitReachedException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'COMPARISON_LIMIT_REACHED';
    }

    protected function summary(): string
    {
        return 'The comparison already holds the maximum number of products';
    }
}
