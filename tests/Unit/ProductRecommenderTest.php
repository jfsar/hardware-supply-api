<?php

namespace Tests\Unit;

use App\Enums\RelationType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\ProductVariant;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Services\Recommendations\ProductRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductRecommenderTest extends TestCase
{
    use RefreshDatabase;

    private function recommend(Product $source, ?User $user = null, ?string $sessionHash = null): array
    {
        return app(ProductRecommender::class)
            ->recommend($source, $user, $sessionHash)
            ->map(fn (Product $product): int => (int) $product->getKey())
            ->all();
    }

    private function activeProduct(int $index = 0): Product
    {
        return Product::factory()->active()->create();
    }

    /**
     * A delivered order line coupling two variants, proving co-purchase.
     */
    private function coPurchase(ProductVariant $variant): void
    {
        $user = User::factory()->create();
        OrderItem::factory()
            ->forOrder(Order::factory()->forUser($user)->create())
            ->create([
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'quantity_fulfilled' => 1,
            ]); // one fact in one row; the order pair proves co-purchase
    }

    #[Test]
    public function returns_nothing_when_no_signals_exist(): void
    {
        $source = $this->activeProduct();
        $others = collect(range(0, 2))->map(fn () => $this->activeProduct());

        $result = $this->recommend($source);

        $this->assertSame([], $result);
        $this->assertNotContains($source->id, $result, 'source product is never recommended');
        $this->assertNotContains($others->pluck('id')->all(), $result);
    }

    #[Test]
    public function related_products_outrank_plain_popularity(): void
    {
        $source = $this->activeProduct();
        $related = $this->activeProduct();
        $popular = $this->activeProduct();

        ProductRelation::create([
            'product_id' => $source->id,
            'related_product_id' => $related->id,
            'relation_type' => RelationType::Related,
            'sort_order' => 1,
        ]);

        foreach (range(1, 20) as $ignored) {
            $this->coPurchase($popular->variants()->create(['sku' => 'POP-'.$ignored, 'is_default' => false]));
        }

        $this->assertSame([$related->id, $popular->id], $this->recommend($source));
    }

    #[Test]
    public function frequently_bought_together_beats_a_small_popular_bar(): void
    {
        $source = $this->activeProduct();
        $sourceVariant = $source->variants()->create(['sku' => 'SRC-1']);
        $companion = $this->activeProduct();
        $companionVariant = $companion->variants()->create(['sku' => 'CMP-1']);
        $niche = $this->activeProduct();

        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            OrderItem::factory()
                ->forOrder(Order::factory()->forUser($user)->create())
                ->create([
                    'product_variant_id' => $sourceVariant->id,
                    'quantity' => 1,
                    'quantity_fulfilled' => 1,
                ]);
            OrderItem::factory()
                ->forOrder(Order::factory()->forUser($user)->create())
                ->create([
                    'product_variant_id' => $companionVariant->id,
                    'quantity' => 1,
                    'quantity_fulfilled' => 1,
                ]);
        }

        // Companion co-occurs in 3 shared orders; the niche sells 1 unit alone.
        $this->coPurchase($niche->variants()->create(['sku' => 'NCH-1']));

        $this->assertSame([$companion->id, $niche->id], $this->recommend($source));
    }

    #[Test]
    public function personal_category_affinity_pushes_familiar_categories_up(): void
    {
        $user = User::factory()->create();
        $source = $this->activeProduct();
        $familiar = $this->activeProduct();
        $unfamiliar = $this->activeProduct();

        // The shopper bought two products in $familiar's category (outside the
        // popularity window so only the affinity signal scores them).
        foreach (range(1, 2) as $index) {
            $bought = Product::factory()->active()->inCategory($familiar->category)->create();
            OrderItem::factory()
                ->forOrder(Order::factory()->forUser($user)->create(['placed_at' => now()->subDays(40)]))
                ->create([
                    'product_variant_id' => $bought->variants()->create(['sku' => "BGT-{$index}"])->id,
                    'quantity' => 1,
                    'quantity_fulfilled' => 1,
                ]);
        }

        $result = $this->recommend($source, $user);
        $this->assertSame($familiar->id, $result[0], 'familiar-category products rank first');
        $this->assertNotContains($unfamiliar->id, $result, 'unrelated categories stay out');
    }

    #[Test]
    public function guest_sessions_also_feed_category_affinity(): void
    {
        $source = $this->activeProduct();
        $familiar = $this->activeProduct();

        RecentlyViewedProduct::factory()->forSession(hash('sha256', 'abc'))->create([
            'product_id' => $familiar->id,
            'viewed_at' => now()->subMinute(),
        ]);
        // same as the source category → a lone match to the affinity signal
        $this->coPurchase($source->variants()->create(['sku' => 'SRC-2']));

        $result = $this->recommend($source, null, hash('sha256', 'abc'));
        $this->assertContains($familiar->id, $result);
    }

    #[Test]
    public function drafts_unreleased_and_the_source_are_never_ranked(): void
    {
        $source = $this->activeProduct();
        $sourceVariant = $source->variants()->create(['sku' => 'SRC-3']);
        $draft = Product::factory()->draft()->create();
        $draftVariant = $draft->variants()->create(['sku' => 'DRF-1']);

        $user = User::factory()->create();
        OrderItem::factory()
            ->forOrder(Order::factory()->forUser($user)->create())
            ->create(['product_variant_id' => $sourceVariant->id, 'quantity_fulfilled' => 1]);
        OrderItem::factory()
            ->forOrder(Order::factory()->forUser($user)->create())
            ->create(['product_variant_id' => $draftVariant->id, 'quantity_fulfilled' => 1]);

        $result = $this->recommend($source);
        $this->assertSame([], $result);
        $this->assertNotContains($source->id, $result);
        $this->assertNotContains($draft->id, $result);
    }

    #[Test]
    public function identical_signals_break_ties_by_product_id(): void
    {
        $source = $this->activeProduct();
        $first = $this->activeProduct();
        $second = $this->activeProduct();

        foreach ([$first, $second] as $friendly) {
            ProductRelation::create([
                'product_id' => $source->id,
                'related_product_id' => $friendly->id,
                'relation_type' => RelationType::Accessory,
                'sort_order' => 0,
            ]);
        }

        // Equal (2.0) accessory weight → ascending id wins deterministically.
        $result = $this->recommend($source);
        $this->assertSame([$first->id, $second->id], $result);
    }
}
