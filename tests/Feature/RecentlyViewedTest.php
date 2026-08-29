<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class RecentlyViewedTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    /**
     * Viewing any active product records it for the browsing identity.
     */
    private function visit(Product $product, ?string $cartToken = null, ?User $user = null): void
    {
        $request = $user !== null
            ? $this->actingAsToken($user)
            : $this->withHeader('Cart-Token', $cartToken ?? str_repeat('a', 64));

        $request->getJson("/api/v1/products/{$product->slug}")->assertOk();
    }

    #[Test]
    public function viewing_a_product_records_history_for_a_guest_session(): void
    {
        $token = str_repeat('1', 64);
        $product = Product::factory()->active()->create();

        $this->visit($product, $token);

        $this->assertDatabaseHas('recently_viewed_products', [
            'session_hash' => hash('sha256', $token),
            'product_id' => $product->id,
        ]);

        $response = $this->withHeader('Cart-Token', $token)
            ->getJson('/api/v1/products/recently-viewed');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $this->assertSame($product->ulid, $response->json('data.0.ulid'));
    }

    #[Test]
    public function guest_histories_are_isolated_by_session(): void
    {
        $tokenA = str_repeat('a', 64);
        $tokenB = str_repeat('b', 64);
        $productA = Product::factory()->active()->create();
        $productB = Product::factory()->active()->create();

        $this->visit($productA, $tokenA);
        $this->visit($productB, $tokenB);

        $histories = [
            [$tokenA, $productA->ulid],
            [$tokenB, $productB->ulid],
        ];

        foreach ($histories as [$token, $expectedUlid]) {
            $response = $this->withHeader('Cart-Token', $token)
                ->getJson('/api/v1/products/recently-viewed');

            $response->assertOk();
            $this->assertSame([$expectedUlid], collect($response->json('data'))->pluck('ulid')->all());
        }
    }

    #[Test]
    public function authenticated_history_is_scoped_to_the_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->active()->create();

        $this->visit($product, user: $user);

        // Another account's history does not include this user's views.
        $this->actingAsToken($other)
            ->getJson('/api/v1/products/recently-viewed')
            ->assertJsonPath('meta.total', 0);

        $response = $this->actingAsToken($user)
            ->getJson('/api/v1/products/recently-viewed');

        $response->assertOk();
        $this->assertSame([$product->ulid], collect($response->json('data'))->pluck('ulid')->all());

        $row = RecentlyViewedProduct::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->session_hash);
    }

    #[Test]
    public function newest_views_lead_the_list(): void
    {
        $user = User::factory()->create();
        [$first, $second, $third] = [
            Product::factory()->active()->create(),
            Product::factory()->active()->create(),
            Product::factory()->active()->create(),
        ];

        RecentlyViewedProduct::factory()->forUser($user)->create(['product_id' => $first->id, 'viewed_at' => now()->subMinutes(2)]);
        RecentlyViewedProduct::factory()->forUser($user)->create(['product_id' => $second->id, 'viewed_at' => now()->subMinute()]);
        RecentlyViewedProduct::factory()->forUser($user)->create(['product_id' => $third->id, 'viewed_at' => now()]);

        $ulids = collect(
            $this->actingAsToken($user)
                ->getJson('/api/v1/products/recently-viewed')
                ->json('data'),
        )->pluck('ulid')->all();

        $this->assertEquals([$third->ulid, $second->ulid, $first->ulid], $ulids);
    }
}
