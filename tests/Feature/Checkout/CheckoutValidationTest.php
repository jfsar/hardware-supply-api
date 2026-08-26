<?php

namespace Tests\Feature\Checkout;

use App\Models\Inventory;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class CheckoutValidationTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    /**
     * A cart holding one priced line; returns the request token.
     *
     * @return array{0: string, 1: ProductVariant}
     */
    private function cartWithItem(): array
    {
        $variant = $this->pricedVariant(25000);
        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 2]);

        return [$this->cartTokenFromResponse($added), $variant];
    }

    #[Test]
    public function validation_returns_totals_and_a_signed_token(): void
    {
        [$token] = $this->cartWithItem();

        $response = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'val-key-1')
            ->postJson('/api/v1/checkout/validate');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame(50000, $data['totals']['subtotal_minor']);
        $this->assertSame(50000, $data['totals']['total_minor']);
        $this->assertArrayHasKey('checkout_token', $data);
        $this->assertSame('pending', $data['checkout_session']['status']);
        $this->assertFalse($data['totals']['is_estimated']);
    }

    #[Test]
    public function empty_carts_are_rejected(): void
    {
        $response = $this->postJson('/api/v1/cart/items', [
            'variant' => $this->pricedVariant(100)->ulid,
            'quantity' => 1,
        ]);
        $token = $this->cartTokenFromResponse($response);

        $this->withHeader('Cart-Token', $token)->deleteJson('/api/v1/cart')->assertOk();

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'empty-cart-key')
            ->postJson('/api/v1/checkout/validate')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CART_EMPTY');
    }

    #[Test]
    public function placement_with_stale_totals_is_refused(): void
    {
        [$token, $variant] = $this->cartWithItem();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'stale-key')
            ->postJson('/api/v1/checkout/validate');

        // The base price changes after validation.
        PriceListItem::query()
            ->where('product_variant_id', $variant->id)
            ->update(['price_amount_minor' => 99000]);

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'stale-place-1')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKOUT_TOTALS_CHANGED');
    }

    #[Test]
    public function placement_without_a_valid_token_is_refused(): void
    {
        [$token] = $this->cartWithItem();

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'no-token-1')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => 'not-a-real-token',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHECKOUT_TOTALS_CHANGED');
    }

    #[Test]
    public function session_status_endpoint_reports_lifecycle_to_its_owner(): void
    {
        [$token] = $this->cartWithItem();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'status-val')
            ->postJson('/api/v1/checkout/validate');
        $validated->assertOk();

        $sessionUlid = $validated->json('data.checkout_session.ulid');

        // The guest holding the cart token may inspect the session.
        $this->withHeader('Cart-Token', $token)
            ->getJson("/api/v1/checkout/{$sessionUlid}")
            ->assertOk()
            ->assertJsonPath('data.checkout_session.status', 'pending');

        // Another caller's opaque token cannot inspect it (anti-IDOR); an
        // explicit header overrides any ambient cookie identity.
        $strangerToken = hash('sha256', 'a-totally-different-guest-token');

        $this->withHeader('Cart-Token', $strangerToken)
            ->getJson("/api/v1/checkout/{$sessionUlid}")
            ->assertNotFound();
    }

    #[Test]
    public function stock_drift_fails_placement_safely(): void
    {
        [$token, $variant] = $this->cartWithItem();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'drift-val')
            ->postJson('/api/v1/checkout/validate');

        // Stock evaporates after validation but before placement.
        Inventory::query()->where('product_variant_id', $variant->id)->update([
            'quantity_reserved' => 100,
        ]);

        $placed = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'drift-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'guest@example.test',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ]);

        $placed->assertStatus(409)->assertJsonPath('error.code', 'STOCK_INSUFFICIENT');

        // Nothing leaked: no orders exist after the failed transaction.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }
}
