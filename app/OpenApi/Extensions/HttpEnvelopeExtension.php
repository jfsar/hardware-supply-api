<?php

namespace App\OpenApi\Extensions;

use App\OpenApi\ErrorEnvelope;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Fallback for any HTTP exception without a dedicated envelope extension.
 * Mirrors ApiExceptionRenderer's HttpExceptionInterface branch: the status
 * code decides the stable error code.
 */
final class HttpEnvelopeExtension extends ApiErrorEnvelopeExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(HttpExceptionInterface::class)
            && ! collect(self::SPECIFICALLY_HANDLED)->contains(
                fn (string $exception) => $type->isInstanceOf($exception),
            );
    }

    public function toResponse(Type $type)
    {
        $status = $this->resolveStatus($type);

        if ($status === null) {
            return null;
        }

        return ErrorEnvelope::response($status, $this->summary(), self::codeForStatus($status));
    }

    /**
     * The literal status from an abort(...) call, when inference captured it.
     */
    private function resolveStatus(ObjectType $type): ?int
    {
        /*
         * Index 7 is the honestly-inferred `TCode` template of Symfony's
         * HttpException; index 0 is used when the type is constructed by
         * other Scramble extensions. Mirrors HttpExceptionToResponseExtension.
         */
        $codeType = count($type->templateTypes ?? []) > 3
            ? ($type->templateTypes[7] ?? null)
            : ($type->templateTypes[0] ?? null);

        return $codeType instanceof LiteralIntegerType ? $codeType->value : null;
    }

    /**
     * A generic stable code for unclassified HTTP exceptions.
     */
    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => 'REQUEST_FAILED',
        };
    }

    protected function status(): int
    {
        return 500;
    }

    protected function errorCode(): string
    {
        return 'REQUEST_FAILED';
    }

    protected function summary(): string
    {
        return 'Request failed';
    }

    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return null;
    }
}
