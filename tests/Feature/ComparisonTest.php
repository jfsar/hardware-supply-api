<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ComparisonTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    private const TOKEN = 'c';

    private function guest(): static
    {
        return $this->withHeader('Cart-Token', str_repeat(self::TOKEN, 64));
    }

    private function addProduct(string $ulid): TestResponse
    {
        return $this->guest()->postJson('/api/v1/comparison/items', ['product_ulid' => $ulid]);
    }

    #[Test]
    public function guests_can_compare_products_and_see_the_aligned_matrix(): void
    {
        $product = Product::factory()->active()->create();

        $this->addProduct($product->ulid)->assertCreated();

        $response = $this->guest()->getJson('/api/v1/comparison');

        $response->assertOk();
        $ulids = collect($response->json('data.products'))->pluck('ulid')->all();
        $this->assertSame([$product->ulid], $ulids);
        $this->assertIsArray($response->json('data.attributes'));
    }

    #[Test]
    public function adding_the_same_product_twice_is_idempotent(): void
    {
        $product = Product::factory()->active()->create();

        $this->addProduct($product->ulid)->assertCreated();
        $this->addProduct($product->ulid)->assertOk();

        $this->assertSame(1, collect($this->guest()->getJson('/api/v1/comparison')->json('data.products'))->count());
    }

    #[Test]
    public function comparison_is_capped_and_overflow_is_rejected_with_a_dedicated_code(): void
    {
        $products = collect(range(1, 5))
            ->map(fn () => Product::factory()->active()->create());

        foreach ($products->take(4) as $index => $product) {
            $this->addProduct($product->ulid)->assertCreated();
        }

        $this->addProduct($products[4]->ulid)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'COMPARISON_LIMIT_REACHED');

        $this->assertCount(
            4,
            collect($this->guest()->getJson('/api/v1/comparison')->json('data.products')),
        );
    }

    #[Test]
    public function authenticated_comparisons_are_scoped_to_the_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->active()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/comparison/items', ['product_ulid' => $product->ulid])
            ->assertCreated();

        $this->actingAsToken($other)
            ->getJson('/api/v1/comparison')
            ->assertJsonPath('data.products', []);

        $response = $this->actingAsToken($user)->getJson('/api/v1/comparison');
        $this->assertSame([$product->ulid], collect($response->json('data.products'))->pluck('ulid')->all());
    }

    #[Test]
    public function products_can_be_removed_and_remove_is_a_noop_when_absent(): void
    {
        $product = Product::factory()->active()->create();

        $this->addProduct($product->ulid)->assertCreated();

        $this->guest()
            ->deleteJson("/api/v1/comparison/items/{$product->ulid}")
            ->assertOk();

        $this->assertSame([], collect($this->guest()->getJson('/api/v1/comparison')->json('data.products'))->all());

        $this->guest()
            ->deleteJson("/api/v1/comparison/items/{$product->ulid}")
            ->assertOk();
    }
}
