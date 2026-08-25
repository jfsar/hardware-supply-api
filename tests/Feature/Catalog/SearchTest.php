<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_matches_name_and_short_description(): void
    {
        Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create([
            'name' => 'Cordless Drill 18V',
            'slug' => 'cordless-drill-18v',
        ]);
        Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create([
            'name' => 'Workbench Vise',
            'short_description' => 'Heavy duty drill press companion',
            'slug' => 'workbench-vise',
        ]);
        Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create([
            'name' => 'Garden Hose',
            'slug' => 'garden-hose',
        ]);

        $byName = collect($this->getJson('/api/v1/products?q=drill')->json('data'))->pluck('slug');
        $this->assertContains('cordless-drill-18v', $byName);
        $this->assertNotContains('garden-hose', $byName);

        $byDescription = $this->getJson('/api/v1/products?q=drill+press');
        $this->assertContains('workbench-vise', collect($byDescription->json('data'))->pluck('slug'));
    }

    public function test_draft_and_archived_products_never_match(): void
    {
        Product::factory()->draft()->create(['name' => 'Secret Drill', 'slug' => 'secret-drill']);
        Product::factory()->archived()->create(['name' => 'Old Drill', 'slug' => 'old-drill']);

        $slugs = collect($this->getJson('/api/v1/products?q=drill')->json('data'))->pluck('slug');

        $this->assertNotContains('secret-drill', $slugs);
        $this->assertNotContains('old-drill', $slugs);
    }

    public function test_category_and_brand_filters_apply(): void
    {
        $tools = Category::factory()->create(['name' => 'Tools', 'slug' => 'tools']);
        $garden = Category::factory()->create(['name' => 'Garden', 'slug' => 'garden']);
        $bosch = Brand::factory()->create(['name' => 'Bosch', 'slug' => 'bosch']);
        $makita = Brand::factory()->create(['name' => 'Makita', 'slug' => 'makita']);

        Product::factory()->active()->inCategory($tools)->create(['brand_id' => $bosch->id, 'slug' => 'tool-bosch']);
        Product::factory()->active()->inCategory($tools)->create(['brand_id' => $makita->id, 'slug' => 'tool-makita']);
        Product::factory()->active()->inCategory($garden)->create(['brand_id' => $bosch->id, 'slug' => 'garden-bosch']);

        $byCategory = collect($this->getJson('/api/v1/products?category=tools')->json('data'))->pluck('slug');
        $this->assertEqualsCanonicalizing(['tool-bosch', 'tool-makita'], $byCategory->all());

        $byBrand = collect($this->getJson('/api/v1/products?brands[]=bosch&category=tools')->json('data'))->pluck('slug');
        $this->assertSame(['tool-bosch'], $byBrand->all());
    }

    public function test_facets_are_returned_alongside_results(): void
    {
        $tools = Category::factory()->create(['name' => 'Tools', 'slug' => 'tools']);
        $paint = Category::factory()->create(['name' => 'Paint', 'slug' => 'paint']);
        $bosch = Brand::factory()->create(['name' => 'Bosch', 'slug' => 'bosch']);

        Product::factory()->count(3)->active()->inCategory($tools)->create(['brand_id' => $bosch->id]);
        Product::factory()->count(2)->active()->inCategory($paint)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $categories = collect($response->json('facets.categories'));
        $toolsFacet = $categories->firstWhere('slug', 'tools');

        $this->assertNotNull($toolsFacet);
        $this->assertSame(3, $toolsFacet['count']);
        $this->assertCount(2, $response->json('facets.categories'));
        $this->assertNotEmpty($response->json('facets.brands'));

        // Facets honour the other dimension's filters.
        $filtered = $this->getJson('/api/v1/products?category=paint');
        $brandFacets = collect($filtered->json('facets.brands'));

        $this->assertNull($brandFacets->firstWhere('slug', 'bosch'));
    }

    public function test_sort_allowlist_is_enforced(): void
    {
        Product::factory()->count(3)->active()->has(ProductVariant::factory(), 'variants')->create();

        foreach (['relevance', 'price_asc', 'price_desc', 'newest'] as $sort) {
            $this->getJson("/api/v1/products?sort={$sort}")->assertOk();
        }

        $this->getJson('/api/v1/products?sort=name_asc')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_newest_sort_orders_by_publication_recency(): void
    {
        $older = Product::factory()->active()->create([
            'slug' => 'older-product',
            'published_at' => now()->subDays(5),
        ]);
        $newer = Product::factory()->active()->create([
            'slug' => 'newer-product',
            'published_at' => now()->subDay(),
        ]);

        $slugs = collect($this->getJson('/api/v1/products?sort=newest')->json('data'))->pluck('slug');

        $this->assertEqualsCanonicalizing(
            ['newer-product', 'older-product'],
            $slugs->take(2)->values()->all(),
        );
        $this->assertSame('newer-product', $slugs->first());
    }

    public function test_per_page_is_capped_at_one_hundred(): void
    {
        Product::factory()->count(3)->active()->has(ProductVariant::factory(), 'variants')->create();

        $this->getJson('/api/v1/products?per_page=500')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->getJson('/api/v1/products?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_pagination_metadata_is_returned(): void
    {
        Product::factory()->count(4)->active()->has(ProductVariant::factory(), 'variants')->create();

        $response = $this->getJson('/api/v1/products?page=2&per_page=3');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.total', 4);
        $response->assertJsonCount(1, 'data');
    }

    public function test_autocomplete_returns_slug_and_name_pairs(): void
    {
        Product::factory()->active()->create(['name' => 'Claw Hammer Pro', 'slug' => 'claw-hammer-pro']);
        Product::factory()->draft()->create(['name' => 'Claw Hammer Draft', 'slug' => 'claw-hammer-draft']);

        $response = $this->getJson('/api/v1/search/autocomplete?q=claw');

        $response->assertOk();
        $results = $response->json('data');

        $this->assertCount(1, $results);
        $this->assertSame(['slug' => 'claw-hammer-pro', 'name' => 'Claw Hammer Pro'], $results[0]);
    }

    public function test_autocomplete_requires_a_term(): void
    {
        $this->getJson('/api/v1/search/autocomplete')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
