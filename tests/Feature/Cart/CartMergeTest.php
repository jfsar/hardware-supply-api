<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    #[Test]
    public function guest_cart_merges_into_user_cart_after_login(): void
    {
        $variant = $this->pricedVariant(25000);
        $user = User::factory()->create();

        $guest = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 2]);
        $token = $this->cartTokenFromResponse($guest);

        $login = $this->withHeader('Cart-Token', $token)
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $login->assertOk();

        $merged = Cart::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($merged, 'user cart created by merge');
        $this->assertSame(2.0, (float) $merged->items()->where('product_variant_id', $variant->id)->sum('quantity'));

        // The guest cart is gone.
        $this->assertNull(Cart::query()
            ->whereNull('user_id')
            ->where('session_token_hash', hash('sha256', $token))
            ->first());
    }

    #[Test]
    public function collisions_sum_then_cap_to_available_stock(): void
    {
        $variant = $this->pricedVariant(25000, stock: 5.0);
        $user = User::factory()->create();

        CartItem::query()->create([
            'cart_id' => Cart::factory()->forUser($user)->create()->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $guest = $this->postJson('/api/v1/cart/items', ['variant' => $variant->ulid, 'quantity' => 4]);
        $token = $this->cartTokenFromResponse($guest);

        $this->withHeader('Cart-Token', $token)->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $quantity = (float) Cart::query()->where('user_id', $user->id)
            ->firstOrFail()
            ->items()
            ->where('product_variant_id', $variant->id)
            ->value('quantity');

        $this->assertEquals(5.0, $quantity, '3 + 4 capped to stock of 5');
    }

    #[Test]
    public function login_without_a_guest_token_leaves_carts_alone(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(0, Cart::query()->where('user_id', $user->id)->count());
    }
}
