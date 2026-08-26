<?php

namespace Tests\Feature\Cart;

use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    #[Test]
    public function guest_receives_cart_token_and_can_add_items(): void
    {
        $variant = $this->pricedVariant(25000);

        $response = $this->postJson('/api/v1/cart/items', [
            'variant' => $variant->ulid,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $token = $this->cartTokenFromResponse($response);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        $payload = $response->json('data');
        $this->assertSame(1, count($payload['cart']['items']));
        $this->assertSame(50000, $payload['totals']['subtotal_minor']);
        // Preview totals are always flagged non-authoritative (FR-CART-005).
        $this->assertTrue($payload['totals']['is_estimated']);
    }

    #[Test]
    public function duplicate_variants_merge_into_one_row_capped_at_stock(): void
    {
        $variant = $this->pricedVariant(25000, stock: 6.0);

        $first = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 4]);
        $token = $this->cartTokenFromResponse($first);

        $second = $this->withHeader('Cart-Token', $token)
            ->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 4]);

        $cartId = CartItem::query()->value('cart_id');
        $items = CartItem::query()->where('cart_id', $cartId)->get();
        $this->assertSame(1, $items->count());
        $this->assertEquals(6.0, $items->first()->quantity, 'quantity capped at available stock');
    }

    #[Test]
    public function quantity_updates_are_capped_at_availability(): void
    {
        $variant = $this->pricedVariant(25000, stock: 3.0);

        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $token = $this->cartTokenFromResponse($added);
        $itemId = $added->json('data.cart.items.0.id');

        $updated = $this->withHeader('Cart-Token', $token)
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 99]);

        $updated->assertOk();
        $this->assertEquals(3.0, $updated->json('data.cart.items.0.quantity'));
    }

    #[Test]
    public function items_can_be_removed_and_the_cart_cleared(): void
    {
        $variant = $this->pricedVariant(25000);

        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $request = fn () => $this->withHeader('Cart-Token', $this->cartTokenFromResponse($added));
        $itemId = $added->json('data.cart.items.0.id');

        ($request())->deleteJson("/api/v1/cart/items/{$itemId}")->assertOk();
        $this->assertSame(0, count(($request())->getJson('/api/v1/cart')->json('data.cart.items') ?? []));

        // Re-add then clear everything.
        ($request())->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 2])->assertCreated();
        ($request())->deleteJson('/api/v1/cart')->assertOk();
        $this->assertSame(0, count(($request())->getJson('/api/v1/cart')->json('data.cart.items')));
    }

    #[Test]
    public function inactive_variants_are_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['status' => 'inactive']);

        $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VARIANT_NOT_PURCHASABLE');
    }

    #[Test]
    public function zero_stock_variants_report_insufficient_stock(): void
    {
        $variant = $this->pricedVariant(25000, stock: 0.0);

        $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STOCK_INSUFFICIENT');
    }

    #[Test]
    public function another_guest_token_cannot_touch_foreign_lines(): void
    {
        $variant = $this->pricedVariant(25000);

        $added = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 1]);
        $itemId = $added->json('data.cart.items.0.id');

        $this->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 5])
            ->assertNotFound();
    }
}
