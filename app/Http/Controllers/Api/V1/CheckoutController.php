<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Checkout\ValidateCheckout;
use App\Exceptions\Checkout\CartEmptyException;
use App\Exceptions\Checkout\CheckoutTotalsChangedException;
use App\Exceptions\Checkout\PaymentMethodUnavailableException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Pricing\CouponException;
use App\Exceptions\Pricing\PriceUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\ResolveCartToken;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Requests\Checkout\ValidateCheckoutRequest;
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\CheckoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checkout endpoints (Phase 4 Task 8): validate → place → status.
 * Both POSTs sit behind the checkout limiter and idempotency middleware.
 */
class CheckoutController extends Controller
{
    /**
     * Read-only authoritative validation; returns totals bound to a
     * short-lived signed checkout_token.
     *
     * @throws CouponException
     * @throws PriceUnavailableException
     * @throws CartEmptyException
     */
    public function validate(
        ValidateCheckoutRequest $request,
        ValidateCheckout $validateCheckout,
        ResolveCart $resolveCart,
    ): JsonResponse {
        $cart = ($resolveCart)($this->userId($request), $this->tokenHash($request), true);

        $result = ($validateCheckout)($cart, auth('sanctum')->user());

        return response()->json(['data' => [
            'checkout_session' => new CheckoutResource($result['session']),
            'totals' => $result['totals'],
            'checkout_token' => $result['checkout_token'],
            'token_expires_at' => $result['token_expires_at']->toISOString(),
        ]]);
    }

    /**
     * Place the order (SRS §38 steps 12–22) in one transaction.
     *
     * @throws CartEmptyException
     * @throws CheckoutTotalsChangedException
     * @throws PaymentMethodUnavailableException
     * @throws InsufficientStockException
     * @throws CouponException
     */
    public function place(PlaceOrderRequest $request, PlaceOrder $placeOrder, ResolveCart $resolveCart): JsonResponse
    {
        $cart = ($resolveCart)($this->userId($request), $this->tokenHash($request), false);

        if ($cart === null || ! $cart->items()->exists()) {
            throw CartEmptyException::empty();
        }

        /** @var callable(int, string): void|null $recorder */
        $recorder = $request->attributes->get(EnsureIdempotency::RECORDER_ATTRIBUTE);

        $result = ($placeOrder)(
            $cart,
            auth('sanctum')->user(),
            (string) $request->input('payment_method'),
            strtolower(trim((string) (auth('sanctum')->user()?->email ?? $request->input('contact_email')))),
            $request->input('contact_phone'),
            (array) $request->input('address', []),
            (string) $request->input('checkout_token'),
            $recorder,
        );

        [$order, $payment] = [$result['order'], $result['payment']];

        return response()->json(['data' => [
            'order' => new OrderResource($order->loadMissing('items')),
            'payment' => new PaymentResource($payment),
        ]], 201);
    }

    /**
     * Session lifecycle status for the given checkout.
     */
    public function show(Request $request, CheckoutSession $checkout): JsonResponse
    {
        $guestHash = $this->tokenHash($request);

        $ownsSession = ($checkout->user_id !== null && $checkout->user_id === $this->userId($request))
            || ($checkout->user_id === null && $guestHash !== null && $checkout->cart?->session_token_hash === $guestHash);

        abort_unless($ownsSession, 404);

        $checkout->loadMissing('order');

        return response()->json(['data' => [
            'checkout_session' => new CheckoutResource($checkout),
        ]]);
    }

    /**
     * Authenticated caller id or null for guests. Guest-allowed routes
     * run without auth middleware, so the sanctum guard is explicit —
     * the application default guard is web.
     */
    protected function userId(Request $request): ?int
    {
        return auth('sanctum')->user()?->getAuthIdentifier();
    }

    /**
     * The SHA-256 cart token hash attached by global middleware.
     */
    protected function tokenHash(Request $request): ?string
    {
        $hash = $request->attributes->get(ResolveCartToken::HASH_ATTRIBUTE);

        return is_string($hash) ? $hash : null;
    }
}
