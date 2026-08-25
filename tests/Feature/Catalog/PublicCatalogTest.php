<?php

namespace Tests\Feature\Catalog;

use App\Enums\AttributeDataType;
use App\Enums\BundleType;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Support\CategoryTreeCache;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    public function test_only_active_products_are_publicly_visible(): void
    {
        Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create([
            'name' => 'Visible Hammer',
            'slug' => 'visible-hammer',
        ]);
        Product::factory()->draft()->create(['slug' => 'draft-product']);
        Product::factory()->archived()->create(['slug' => 'archived-product']);

        $list = $this->getJson('/api/v1/products');

        $list->assertOk();
        $slugs = collect($list->json('data'))->pluck('slug');
        $this->assertContains('visible-hammer', $slugs);
        $this->assertNotContains('draft-product', $slugs);
        $this->assertNotContains('archived-product', $slugs);

        $detail = $this->getJson('/api/v1/products/visible-hammer');
        $detail->assertOk();
        $this->assertSame('visible-hammer', $detail->json('data.slug'));

        $this->getJson('/api/v1/products/draft-product')->assertNotFound();
        $this->getJson('/api/v1/products/archived-product')->assertNotFound();
    }

    public function test_detail_payload_shape_includes_variants_specs_and_warranty(): void
    {
        $product = Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create([
            'warranty_type' => 'manufacturer',
            'warranty_duration_months' => 24,
        ]);

        $attribute = Attribute::factory()->create([
            'slug' => 'material',
            'data_type' => AttributeDataType::Text,
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'value_text' => 'Steel',
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data['variants']);
        $this->assertArrayHasKey('price', $data['variants'][0]);
        $this->assertNull($data['variants'][0]['availability']);
        $this->assertSame(24, $data['warranty']['duration_months']);
        $this->assertSame('Steel', $data['specs'][0]['value']);
    }

    public function test_related_endpoint_returns_related_products(): void
    {
        $product = Product::factory()->active()->create(['slug' => 'main-drill']);
        $related = Product::factory()->active()->create(['slug' => 'spare-battery']);
        $accessory = Product::factory()->active()->create(['slug' => 'carry-case']);
        Product::factory()->active()->create(['slug' => 'unrelated']);

        DB::table('product_relations')->insert([
            ['product_id' => $product->id, 'related_product_id' => $related->id, 'relation_type' => 'related'],
            ['product_id' => $product->id, 'related_product_id' => $accessory->id, 'relation_type' => 'accessory'],
        ]);

        $response = $this->getJson('/api/v1/products/main-drill/related');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertEqualsCanonicalizing(['spare-battery', 'carry-case'], $slugs->all());
        $this->assertNotContains('unrelated', $slugs);
    }

    public function test_bundle_contents_render_on_detail(): void
    {
        $component = Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create();
        $kit = Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create();

        $bundle = ProductBundle::query()->create([
            'product_id' => $kit->id,
            'bundle_type' => BundleType::Kit->value,
        ]);

        ProductBundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'component_product_variant_id' => $component->variants->first()->id,
            'quantity' => 2,
        ]);

        $response = $this->getJson("/api/v1/products/{$kit->slug}");

        $response->assertOk();
        $bundlePayload = $response->json('data.bundle');
        $this->assertSame('kit', $bundlePayload['type']);
        $this->assertCount(1, $bundlePayload['items']);
        $this->assertEquals(2, $bundlePayload['items'][0]['quantity']);
    }

    public function test_reviews_stub_returns_empty_collection_for_active_product(): void
    {
        Product::factory()->active()->create(['slug' => 'reviewed']);

        $this->getJson('/api/v1/products/reviewed/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_categories_tree_is_cached_and_invalidated_by_mutations(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        Category::factory()->create(['name' => 'Hand Tools', 'slug' => 'hand-tools']);

        $tree = $this->getJson('/api/v1/categories?view=tree');
        $tree->assertOk();
        $this->assertCount(1, $tree->json('data'));

        // Cached copy still holds one root even after a direct insert.
        Category::factory()->create(['name' => 'Power Tools', 'slug' => 'power-tools']);
        $cached = cache()->get(CategoryTreeCache::KEY);
        $this->assertCount(1, $cached);

        // A mutation action flushes the cache; the next read sees all three.
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('slug', 'catalog_manager')->value('id'));

        $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/categories', ['name' => 'Fasteners'])
            ->assertCreated();

        $fresh = $this->getJson('/api/v1/categories?view=tree');
        $this->assertCount(3, $fresh->json('data'));
    }

    public function test_catalog_reads_are_eager_loaded_without_n_plus_one(): void
    {
        Product::factory()->count(5)->active()
            ->has(ProductVariant::factory()->count(2), 'variants')
            ->create();

        DB::enableQueryLog();

        try {
            $this->getJson('/api/v1/products?per_page=25')->assertOk();
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThan(
            15,
            $queries,
            "Catalog listing issued {$queries} queries; expected eager loading (N+1 guard).",
        );
    }
}
