<?php

namespace App\Http\Exceptions;

use App\Exceptions\Auth\SuspendedAccountException;
use App\Exceptions\Auth\TwoFactorRequiredException;
use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\Catalog\CategoryInUseException;
use App\Exceptions\Catalog\ProductNotPublishableException;
use App\Exceptions\Checkout\CartEmptyException;
use App\Exceptions\Checkout\CheckoutTotalsChangedException;
use App\Exceptions\Checkout\PaymentMethodUnavailableException;
use App\Exceptions\Http\IdempotencyConflictException;
use App\Exceptions\Http\IdempotencyKeyRequiredException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\NegativeStockException;
use App\Exceptions\Orders\OrderStateException;
use App\Exceptions\Pricing\CouponException;
use App\Exceptions\Pricing\PriceUnavailableException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiExceptionRenderer
{
    /**
     * Render any throwable using the API error envelope.
     */
    public function render(\Throwable $exception, Request $request): JsonResponse
    {
        [$status, $code, $details] = $this->resolve($exception);

        $message = $this->message($exception, $status);

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
            'request_id' => $this->requestId($request),
        ], $status);
    }

    /**
     * Map an exception to a status code, stable error code, and details.
     *
     * @return array{0: int, 1: string, 2: array<string, mixed>}
     */
    private function resolve(\Throwable $exception): array
    {
        if ($exception instanceof TwoFactorRequiredException) {
            return [409, 'TWO_FACTOR_REQUIRED', ['challenge_token' => $exception->challengeToken]];
        }

        if ($exception instanceof SuspendedAccountException) {
            return [403, 'ACCOUNT_SUSPENDED', []];
        }

        if ($exception instanceof ProductNotPublishableException) {
            return [422, 'PRODUCT_NOT_PUBLISHABLE', []];
        }

        if ($exception instanceof CategoryInUseException) {
            return [409, 'CATEGORY_IN_USE', []];
        }

        if ($exception instanceof InsufficientStockException) {
            return [409, 'STOCK_INSUFFICIENT', $exception->details()];
        }

        if ($exception instanceof NegativeStockException) {
            return [409, 'STOCK_NEGATIVE_NOT_ALLOWED', ['sku' => $exception->sku]];
        }

        if ($exception instanceof VariantNotPurchasableException) {
            return [409, 'VARIANT_NOT_PURCHASABLE', $exception->details()];
        }

        if ($exception instanceof PriceUnavailableException) {
            return [409, 'PRICE_UNAVAILABLE', $exception->details()];
        }

        if ($exception instanceof CouponException) {
            return [409, $exception->errorCode, []];
        }

        if ($exception instanceof CheckoutTotalsChangedException) {
            return [409, 'CHECKOUT_TOTALS_CHANGED', []];
        }

        if ($exception instanceof CartEmptyException) {
            return [409, 'CART_EMPTY', []];
        }

        if ($exception instanceof PaymentMethodUnavailableException) {
            return [422, 'PAYMENT_METHOD_UNAVAILABLE', $exception->details()];
        }

        if ($exception instanceof OrderStateException) {
            return [409, 'ORDER_STATE_INVALID', $exception->details()];
        }

        if ($exception instanceof IdempotencyKeyRequiredException) {
            return [422, 'IDEMPOTENCY_KEY_REQUIRED', []];
        }

        if ($exception instanceof IdempotencyConflictException) {
            return [409, 'IDEMPOTENCY_CONFLICT', []];
        }

        // A lost race on the idempotency unique index means a duplicate
        // financial request executed concurrently; refuse it as conflict.
        if ($exception instanceof QueryException
            && str_contains($exception->getMessage(), 'idempotency_keys_user_id_endpoint_key_unique')
        ) {
            return [409, 'IDEMPOTENCY_CONFLICT', []];
        }

        if ($exception instanceof ValidationException) {
            return [422, 'VALIDATION_ERROR', ['fields' => $exception->errors()]];
        }

        if ($exception instanceof AuthenticationException) {
            return [401, 'UNAUTHENTICATED', []];
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return [403, 'FORBIDDEN', []];
        }

        if ($exception instanceof ThrottleRequestsException) {
            return [429, 'TOO_MANY_REQUESTS', []];
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return [404, 'NOT_FOUND', []];
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return [405, 'METHOD_NOT_ALLOWED', []];
        }

        if ($exception instanceof HttpExceptionInterface) {
            return [$exception->getStatusCode(), $this->codeForStatus($exception->getStatusCode()), []];
        }

        return [500, 'INTERNAL_SERVER_ERROR', []];
    }

    /**
     * Choose the exposed message, hiding internals for unexpected failures.
     */
    private function message(\Throwable $exception, int $status): string
    {
        if ($status >= 500 && ! config('app.debug')) {
            return __('Server Error.');
        }

        $message = $exception->getMessage();

        return $message !== '' && $message !== null ? $message : __('Request failed.');
    }

    /**
     * A generic stable code for unclassified HTTP exceptions.
     */
    private function codeForStatus(int $status): string
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

    /**
     * The correlation id assigned by middleware, or a fresh one.
     */
    private function requestId(Request $request): string
    {
        $existing = $request->attributes->get('request_id');

        return is_string($existing) && $existing !== ''
            ? $existing
            : (string) Str::ulid();
    }
}
