<?php

namespace App\OpenApi\Extensions;

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\RecordsNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotFoundEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && (
                $type->isInstanceOf(RecordsNotFoundException::class)
                || $type->isInstanceOf(NotFoundHttpException::class)
            );
    }

    protected function status(): int
    {
        return 404;
    }

    protected function errorCode(): string
    {
        return 'NOT_FOUND';
    }

    protected function summary(): string
    {
        return 'Not found';
    }
}
