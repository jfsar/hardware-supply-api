<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class CodFlowTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    #[Test]
    public function cod_orders_never_touch_the_gateway(): void
    {
        $user = User::factory()->create();

        $variant = $this->pricedVariant(25000);
        $added = $this->actingAsToken($user)->postJson('/api/v1/cart/items', [
            'variant' => $variant->ulid,
            'quantity' => 1,
        ]);
        $token = $this->cartTokenFromResponse($added);

        $validated = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'cod-val')
            ->postJson('/api/v1/checkout/validate');

        $placed = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'cod-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => strtolower($user->email),
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ])->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();

        // COD payment row: internal provider, no attempts, no session metadata.
        $payment = $order->payments()->where('provider', 'internal')->firstOrFail();
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame(0, PaymentAttempt::query()->where('payment_id', $payment->id)->count());
    }

    #[Test]
    public function a_cod_payment_cannot_open_a_gateway_session(): void
    {
        $user = User::factory()->create();

        $variant = $this->pricedVariant(25000);
        $added = $this->actingAsToken($user)->postJson('/api/v1/cart/items', [
            'variant' => $variant->ulid,
            'quantity' => 1,
        ]);
        $token = $this->cartTokenFromResponse($added);

        $validated = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'cod2-val')
            ->postJson('/api/v1/checkout/validate');

        $placed = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'cod2-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => strtolower($user->email),
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ])->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'cod-session')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENT_STATE_INVALID');
    }
}
