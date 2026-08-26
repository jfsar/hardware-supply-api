<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ApplyCoupon;
use App\Actions\Cart\ClearCart;
use App\Actions\Cart\RemoveCartItem;
use App\Actions\Cart\RemoveCoupon;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItem;
use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Pricing\CouponException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCartToken;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Pricing\CartTotalsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-accessible cart endpoints (Phase 4 Task 2). Identity comes from
 * the global cart-token middleware or the authenticated account.
 */
class CartController extends Controller
{
    /**
     * Current cart with estimated preview totals (FR-CART-005).
     */
    public function show(Request $request, ResolveCart $resolveCart, CartTotalsCalculator $calculator): JsonResponse
    {
        [$cart, $payload] = $this->current($request, $resolveCart, $calculator, create: true);

        return response()->json(['data' => $payload]);
    }

    /**
     * Add a purchasable variant to the cart.
     *
     * @throws VariantNotPurchasableException
     * @throws InsufficientStockException
     */
    public function storeItem(
        StoreCartItemRequest $request,
        AddToCart $addToCart,
        ResolveCart $resolveCart,
        CartTotalsCalculator $calculator,
    ): JsonResponse {
        $variant = ProductVariant::query()
            ->where('ulid', (string) $request->input('variant'))
            ->firstOrFail();

        [$cart, $payload] = $this->current($request, $resolveCart, $calculator);

        ($addToCart)($cart, $variant, (float) $request->input('quantity'));

        [$cart, $payload] = $this->current($request, $resolveCart, $calculator);

        return response()->json(['data' => $payload], 201);
    }

    /**
     * Replace a line quantity, re-checked against availability.
     *
     * @throws VariantNotPurchasableException
     * @throws InsufficientStockException
     */
    public function updateItem(
        UpdateCartItemRequest $request,
        CartItem $item,
        UpdateCartItem $updateCartItem,
        ResolveCart $resolveCart,
        CartTotalsCalculator $calculator,
    ): JsonResponse {
        $cart = $this->ownedCartOrFail($request, $resolveCart, $item);

        ($updateCartItem)($item, (float) $request->input('quantity'));

        [, $payload] = $this->fromCart($cart->refresh(), $calculator);

        return response()->json(['data' => $payload]);
    }

    /**
     * Remove one line from the cart.
     */
    public function destroyItem(
        Request $request,
        CartItem $item,
        RemoveCartItem $removeCartItem,
        ResolveCart $resolveCart,
    ): JsonResponse {
        $this->ownedCartOrFail($request, $resolveCart, $item);

        ($removeCartItem)($item);

        return response()->json(['data' => ['message' => __('Item removed.')]]);
    }

    /**
     * Empty the cart entirely.
     */
    public function destroy(Request $request, ClearCart $clearCart, ResolveCart $resolveCart): JsonResponse
    {
        $cart = $this->requireCart($request, $resolveCart);

        if ($cart !== null) {
            ($clearCart)($cart);
        }

        return response()->json(['data' => ['message' => __('Cart cleared.')]]);
    }

    /**
     * Attach a coupon to the cart.
     *
     * @throws CouponException
     */
    public function storeCoupon(
        ApplyCouponRequest $request,
        ApplyCoupon $applyCoupon,
        ResolveCart $resolveCart,
    ): JsonResponse {
        $cart = $this->requireCart($request, $resolveCart) ?? $this->createCart($request, $resolveCart);

        $coupon = ($applyCoupon)($cart, (string) $request->input('code'), auth('sanctum')->user());

        return response()->json(['data' => [
            'message' => __('Coupon applied.'),
            'coupon' => ['code' => $coupon->code],
        ]], 201);
    }

    /**
     * Detach any coupons from the cart.
     */
    public function destroyCoupon(Request $request, RemoveCoupon $removeCoupon, ResolveCart $resolveCart): JsonResponse
    {
        $cart = $this->requireCart($request, $resolveCart);

        if ($cart !== null) {
            ($removeCoupon)($cart);
        }

        return response()->json(['data' => ['message' => __('Coupon removed.')]]);
    }

    /**
     * The caller's cart plus its preview totals payload.
     *
     * @return array{0: Cart|null, 1: array<string, mixed>}
     */
    protected function current(
        Request $request,
        ResolveCart $resolveCart,
        CartTotalsCalculator $calculator,
        bool $create = true,
    ): array {
        $cart = $this->requireCart($request, $resolveCart, $create);

        if ($cart === null) {
            return [null, [
                'cart' => null,
                'totals' => [
                    'lines' => [],
                    'subtotal_minor' => 0,
                    'discount_minor' => 0,
                    'shipping_minor' => 0,
                    'tax_minor' => 0,
                    'total_minor' => 0,
                    'currency_code' => (string) config('commerce.currency', 'PHP'),
                    'is_estimated' => true,
                ],
            ]];
        }

        [$cart, $payload] = $this->fromCart($cart, $calculator);

        return [$cart, $payload];
    }

    /**
     * Build the standard {cart, totals} payload for an existing cart.
     *
     * @return array{0: Cart, 1: array<string, mixed>}
     */
    protected function fromCart(Cart $cart, CartTotalsCalculator $calculator): array
    {
        /** @var array<string, mixed> $totals */
        $totals = ($calculator)->calculate($cart, auth('sanctum')->user(), true);

        $cart->loadMissing(['items.variant', 'couponRows.coupon']);

        return [$cart, [
            'cart' => new CartResource($cart),
            'totals' => $totals,
        ]];
    }

    /**
     * Resolve the caller's cart without creating one unless asked.
     */
    protected function requireCart(Request $request, ResolveCart $resolveCart, bool $create = false): ?Cart
    {
        return $create
            ? $this->createCart($request, $resolveCart)
            : ($resolveCart)(auth('sanctum')->user()?->getAuthIdentifier(), $this->tokenHash($request), false);
    }

    /**
     * Create-or-fetch the caller's cart.
     */
    protected function createCart(Request $request, ResolveCart $resolveCart): Cart
    {
        $cart = ($resolveCart)(auth('sanctum')->user()?->getAuthIdentifier(), $this->tokenHash($request), true);

        abort_if($cart === null, 404);

        return $cart;
    }

    /**
     * Assert a cart line belongs to the caller's cart (anti-IDOR).
     */
    protected function ownedCartOrFail(Request $request, ResolveCart $resolveCart, CartItem $item): Cart
    {
        $cart = $this->requireCart($request, $resolveCart);

        abort_unless($cart !== null && (int) $item->cart_id === (int) $cart->getKey(), 404);

        return $cart;
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
