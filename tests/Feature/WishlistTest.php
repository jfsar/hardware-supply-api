<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    #[Test]
    public function signed_in_customer_can_save_and_list_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create();

        $added = $this->actingAsToken($user)
            ->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid]);

        $added->assertCreated();
        $added->assertJsonPath('data.product_ulid', $product->ulid);

        $response = $this->actingAsToken($user)
            ->getJson('/api/v1/wishlist');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame($product->ulid, $response->json('data.0.ulid'));
    }

    #[Test]
    public function saving_again_is_idempotent(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid])
            ->assertCreated();

        $this->actingAsToken($user)
            ->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid])
            ->assertOk();

        $this->assertDatabaseCount('wishlist_items', 1);

        $wishlist = Wishlist::query()->where('user_id', $user->id)->sole();
        $this->assertSame(1, $wishlist->items()->count());
    }

    #[Test]
    public function removing_a_saved_product_works_and_absent_items_are_a_noop(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid])
            ->assertCreated();

        $this->actingAsToken($user)
            ->deleteJson("/api/v1/wishlist/items/{$product->ulid}")
            ->assertOk();

        $this->assertSame(0, WishlistItem::query()->count());

        $this->actingAsToken($user)
            ->deleteJson("/api/v1/wishlist/items/{$product->ulid}")
            ->assertOk();
    }

    #[Test]
    public function guests_cannot_touch_the_wishlist(): void
    {
        $product = Product::factory()->active()->create();

        $this->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid])
            ->assertUnauthorized();
        $this->getJson('/api/v1/wishlist')->assertUnauthorized();
    }

    #[Test]
    public function unreleased_products_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->draft()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/wishlist/items', ['product_ulid' => $product->ulid])
            ->assertNotFound();
    }
}
