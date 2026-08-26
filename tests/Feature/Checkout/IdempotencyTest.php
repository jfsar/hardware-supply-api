<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    /**
     * A cart with one line; returns the request token.
     */
    private function cartToken(): string
    {
        $variant = $this->pricedVariant(25000);

        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);

        return $this->cartTokenFromResponse($added);
    }

    #[Test]
    public function mutating_checkout_endpoints_require_the_header(): void
    {
        $token = $this->cartToken();

        $this->withHeader('Cart-Token', $token)
            ->postJson('/api/v1/checkout/validate')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');

        $this->withHeader('Cart-Token', $token)
            ->postJson('/api/v1/checkout', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    #[Test]
    public function replayed_keys_return_the_stored_response_verbatim(): void
    {
        $token = $this->cartToken();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'replay-val')
            ->postJson('/api/v1/checkout/validate');
        $validated->assertOk();

        $payload = [
            'payment_method' => 'cod',
            'contact_email' => 'guest@example.test',
            'address' => $this->shippingAddress(),
            'checkout_token' => $validated->json('data.checkout_token'),
        ];

        $first = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'replay-place')
            ->postJson('/api/v1/checkout', $payload);
        $first->assertCreated();

        $second = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'replay-place')
            ->postJson('/api/v1/checkout', $payload);

        $second->assertCreated();
        // Identical stored business payload (request_id is per-response
        // correlation metadata and intentionally fresh).
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame('true', $second->headers->get('X-Idempotency-Replay'));

        // No double execution: exactly one order exists.
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    #[Test]
    public function reused_keys_with_different_payloads_conflict(): void
    {
        $token = $this->cartToken();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'conflict-val')
            ->postJson('/api/v1/checkout/validate');

        $payload = [
            'payment_method' => 'cod',
            'contact_email' => 'guest@example.test',
            'address' => $this->shippingAddress(),
            'checkout_token' => $validated->json('data.checkout_token'),
        ];

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'conflict-place')
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated();

        $drifted = $payload;
        $drifted['contact_email'] = 'someoneelse@example.test';

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'conflict-place')
            ->postJson('/api/v1/checkout', $drifted)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }
}
