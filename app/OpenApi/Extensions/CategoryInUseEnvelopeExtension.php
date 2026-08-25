<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Catalog\CategoryInUseException;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class CategoryInUseEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(CategoryInUseException::class);
    }

    protected function status(): int
    {
        return 409;
    }

    protected function errorCode(): string
    {
        return 'CATEGORY_IN_USE';
    }

    protected function summary(): string
    {
        return 'Category still in use';
    }
}
