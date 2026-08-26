<?php

namespace Tests\Feature\Checkout;

use App\Events\OrderCreated;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PriceListItem;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    /**
     * Run validate then place for a one-line cart; returns both responses.
     *
     * @return array{0: TestResponse, 1: string}
     */
    private function checkoutFlow(string $idempotencyKey = 'place-key', array $overrides = [], ?User $user = null): array
    {
        $variant = $this->pricedVariant(25000);

        $addRequest = $user === null ? $this : $this->actingAsToken($user);
        $added = $addRequest->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 2]);
        $cartToken = $this->cartTokenFromResponse($added);

        $validated = $this->withHeader('Cart-Token', $cartToken)
            ->withHeader('Idempotency-Key', 'validate-'.$idempotencyKey)
            ->postJson('/api/v1/checkout/validate');

        $payload = array_merge([
            'payment_method' => 'cod',
            'contact_email' => 'guest@example.test',
            'contact_phone' => '+639170000000',
            'address' => $this->shippingAddress(),
            'checkout_token' => $validated->json('data.checkout_token'),
        ], $overrides);

        $placed = $this->withHeader('Cart-Token', $cartToken)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/checkout', $payload);

        return [$placed, $cartToken];
    }

    #[Test]
    public function guest_cod_checkout_creates_order_payment_and_reservations_in_one_transaction(): void
    {
        Event::fake([OrderCreated::class]);

        [$placed] = $this->checkoutFlow('guest-cod');

        $placed->assertCreated();

        $data = $placed->json('data');
        $order = Order::query()->where('ulid', $data['order']['ulid'])->firstOrFail();

        // Totals recomputed server-side; client totals ignored entirely.
        $this->assertSame(50000, $order->total_minor);
        $this->assertSame('awaiting_payment', $data['order']['order_status']);
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertNull($order->user_id, 'guest order has no owning account');
        $this->assertSame('guest@example.test', $order->customer_email);

        // Payment row: COD → pending with internal provider.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'provider' => 'internal',
            'status' => 'pending',
            'amount_minor' => 50000,
        ]);

        // Snapshots (FR-ORD-002).
        $item = $order->items()->firstOrFail();
        $this->assertSame(25000, $item->unit_price_minor);
        $this->assertSame(50000, $item->line_total_minor);
        $this->assertNotNull($item->sku_snapshot);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'address_type' => 'shipping',
            'recipient_name' => 'Juan Dela Cruz',
        ]);

        // Reservations linked to the order and holding stock.
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(2.0, (float) $reservation->quantity);
        $this->assertTrue($reservation->status->isActive());

        // Lifecycle history recorded.
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'awaiting_payment',
        ]);

        // Cart cleared after commit; the completed session links to it.
        $cartId = CheckoutSession::query()
            ->whereKey($order->checkout_session_id)
            ->value('cart_id');
        $this->assertNotNull($cartId);
        $this->assertSame(0, CartItem::query()->where('cart_id', $cartId)->count());

        Event::assertDispatched(OrderCreated::class);
    }

    #[Test]
    public function authenticated_checkout_owns_the_order_and_clears_cart(): void
    {
        $user = User::factory()->create();

        [$placed] = $this->checkoutFlow('auth-cod', user: $user);

        $placed->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(strtolower($user->email), $order->customer_email);

        // The customer's cart is emptied after a successful commit.
        $this->assertSame(
            0,
            Cart::query()->where('user_id', $user->id)->withTrashed()->get()
                ->sum(fn (Cart $cart) => $cart->items()->count()),
        );
    }

    #[Test]
    public function coupon_redemption_is_written_inside_checkout(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();
        Coupon::factory()->backedBy($promotion)->create(['code' => 'TAKE10']);

        [$added] = [$this->postJson('/api/v1/cart/items', [
            'variant' => $this->pricedVariant(20000)->ulid,
            'quantity' => 1,
        ])];
        $token = $this->cartTokenFromResponse($added);
        $request = fn () => $this->withHeader('Cart-Token', $token);

        ($request())->postJson('/api/v1/cart/coupon', ['code' => 'TAKE10'])->assertCreated();

        $validated = ($request())->withHeader('Idempotency-Key', 'coupon-val')->postJson('/api/v1/checkout/validate');
        $validated->assertOk();
        $this->assertSame(2000, $validated->json('data.totals.coupon_discount_minor'));

        $placed = ($request())->withHeader('Idempotency-Key', 'coupon-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'coupon@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ]);

        $placed->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();
        $this->assertSame(2000, $order->discount_minor);
        $this->assertSame(18000, $order->total_minor);

        $this->assertDatabaseCount('coupon_redemptions', 1);
        $redemption = CouponRedemption::query()->firstOrFail();
        $this->assertSame($order->id, $redemption->order_id);
        $this->assertSame(2000, $redemption->discount_amount_minor);
    }

    #[Test]
    public function gateway_methods_place_payrex_payments_when_enabled(): void
    {
        $variant = $this->pricedVariant(25000);
        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $token = $this->cartTokenFromResponse($added);

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'gw-val')
            ->postJson('/api/v1/checkout/validate');

        $placed = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'gw-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ]);

        // Phase 5: gateway methods open their flow after placement.
        $placed->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'payrex',
            'payment_method' => 'card',
            'status' => 'pending',
            'amount_minor' => 25000,
        ]);
    }

    #[Test]
    public function gateway_methods_are_rejected_when_the_stack_is_disabled(): void
    {
        config(['payments.enabled' => false]);

        $variant = $this->pricedVariant(25000);
        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $token = $this->cartTokenFromResponse($added);

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'gwd-val')
            ->postJson('/api/v1/checkout/validate');

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'gwd-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_METHOD_UNAVAILABLE');
    }

    #[Test]
    public function failed_placement_leaves_no_financial_traces(): void
    {
        // No price list item exists → totals pipeline throws mid-flight.
        $variant = $this->pricedVariant(25000);
        PriceListItem::query()->delete();

        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $token = $this->cartTokenFromResponse($added);

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'boom-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => 'irrelevant-after-totals-failure',
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }
}
