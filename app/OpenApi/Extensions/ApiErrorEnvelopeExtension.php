<?php

namespace App\OpenApi\Extensions;

use App\Exceptions\Auth\SuspendedAccountException;
use App\Exceptions\Auth\TwoFactorRequiredException;
use App\Exceptions\Catalog\CategoryInUseException;
use App\Exceptions\Catalog\ProductNotPublishableException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\NegativeStockException;
use App\OpenApi\ErrorEnvelope;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base for extensions that render an exception as the API error envelope.
 * Each subclass mirrors one branch of ApiExceptionRenderer::resolve().
 */
abstract class ApiErrorEnvelopeExtension extends ExceptionToResponseExtension
{
    /**
     * Exceptions owned by a dedicated envelope extension; the generic HTTP
     * fallback must not claim them.
     *
     * @var list<class-string<\Throwable>>
     */
    public const SPECIFICALLY_HANDLED = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        RecordsNotFoundException::class,
        NotFoundHttpException::class,
        AccessDeniedHttpException::class,
        TwoFactorRequiredException::class,
        SuspendedAccountException::class,
        ProductNotPublishableException::class,
        CategoryInUseException::class,
        InsufficientStockException::class,
        NegativeStockException::class,
    ];

    abstract protected function status(): int;

    abstract protected function errorCode(): string;

    abstract protected function summary(): string;

    /**
     * The error.details schema contributed by this exception, if any.
     */
    protected function details(Type $type): ?OpenApiTypes\Type
    {
        return null;
    }

    public function toResponse(Type $type)
    {
        return ErrorEnvelope::response($this->status(), $this->summary(), $this->errorCode(), $this->details($type));
    }

    public function reference(ObjectType $type)
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }
}
