<?php

namespace Tests\Feature\Admin;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ManagesCatalog;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use ManagesCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalogPermissions();
    }

    /**
     * A valid create payload with two variants.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Claw Hammer 16oz',
            'category_id' => Category::factory()->create()->id,
            'brand_id' => null,
            'short_description' => 'Forged steel claw hammer',
            'warranty_type' => 'manufacturer',
            'warranty_duration_months' => 12,
            'attributes' => [
                ['attribute_id' => Attribute::factory()->create(['data_type' => 'text'])->id, 'value' => 'Steel'],
            ],
            'variants' => [
                [
                    'sku' => 'HAM-16-STD',
                    'cost_amount_minor' => 125000,
                    'cost_currency_code' => 'PHP',
                    'is_default' => true,
                ],
                [
                    'sku' => 'HAM-16-FG',
                    'cost_amount_minor' => 130000,
                    'cost_currency_code' => 'PHP',
                ],
            ],
        ];
    }

    public function test_manager_creates_a_draft_product_with_nested_variants(): void
    {
        $manager = $this->catalogManager();

        $response = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/products', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.slug', 'claw-hammer-16oz');
        $response->assertJsonCount(2, 'data.variants');

        $product = Product::query()->sole();

        $this->assertSame('draft', $product->status->value);
        $this->assertTrue($product->variants()->where('sku', 'HAM-16-STD')->value('is_default'));
        $this->assertFalse($product->variants()->where('sku', 'HAM-16-FG')->value('is_default'));

        $this->assertDatabaseCount('product_attribute_values', 1);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'product.created')->where('resource_id', $product->id)->count(),
        );
    }

    public function test_slug_collision_receives_numeric_suffix(): void
    {
        $manager = $this->catalogManager();
        Category::factory()->create();

        $payload = $this->payload();
        $first = $this->actingAsToken($manager)->postJson('/api/v1/admin/products', $payload);
        $first->assertCreated();

        // Distinct SKUs; only the slug collides this time.
        $payload['variants'][0]['sku'] = 'HAM-16-STD-B';
        $payload['variants'][1]['sku'] = 'HAM-16-FG-B';

        $second = $this->actingAsToken($manager)->postJson('/api/v1/admin/products', $payload);
        $second->assertCreated();

        $this->assertSame('claw-hammer-16oz-2', $second->json('data.slug'));
    }

    public function test_unknown_fields_are_rejected_strictly(): void
    {
        $manager = $this->catalogManager();

        $payload = $this->payload() + ['barcode' => '1234567890'];

        $response = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/products', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('barcode', $response->json('error.details.fields'));

        $variantPayload = $this->payload();
        $variantPayload['variants'][0]['video_url'] = 'https://example.com/video';

        $nested = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/products', $variantPayload);

        $nested->assertStatus(422);
        $this->assertArrayHasKey('variants.0.video_url', $nested->json('error.details.fields'));
    }

    public function test_duplicate_skus_are_rejected_case_insensitively(): void
    {
        $manager = $this->catalogManager();

        $payload = $this->payload();
        $payload['variants'][1]['sku'] = strtolower((string) $payload['variants'][0]['sku']);

        $response = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/products', $payload);

        $response->assertStatus(422);
        $this->assertArrayHasKey('variants.1.sku', $response->json('error.details.fields'));
    }

    public function test_typed_attribute_values_are_validated_against_data_type(): void
    {
        $manager = $this->catalogManager();

        $attribute = Attribute::factory()->create(['data_type' => 'integer']);
        $payload = $this->payload();
        $payload['attributes'] = [['attribute_id' => $attribute->id, 'value' => 'not-an-int']];

        $response = $this->actingAsToken($manager)
            ->postJson('/api/v1/admin/products', $payload);

        $response->assertStatus(422);
        $this->assertArrayHasKey('attributes.0.value', $response->json('error.details.fields'));
    }

    public function test_publishing_without_active_variant_is_rejected(): void
    {
        $manager = $this->catalogManager();

        $product = Product::factory()
            ->draft()
            ->has(ProductVariant::factory()->state(['status' => 'inactive']), 'variants')
            ->create();

        $response = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/products/{$product->ulid}/publish");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'PRODUCT_NOT_PUBLISHABLE');
        $this->assertSame('draft', $product->fresh()->status->value);
    }

    public function test_full_lifecycle_publish_unpublish_archive_restore_is_audited(): void
    {
        $manager = $this->catalogManager();
        $product = Product::factory()->draft()->create();
        ProductVariant::factory()->forProduct($product)->create();

        $base = "/api/v1/admin/products/{$product->ulid}";

        $this->actingAsToken($manager)->postJson("{$base}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertNotNull($product->fresh()->published_at);

        $this->actingAsToken($manager)->postJson("{$base}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        // Public APIs hide non-active products.
        $this->actingAsToken($manager)->getJson("/api/v1/products/{$product->slug}")
            ->assertNotFound();

        $this->actingAsToken($manager)->deleteJson($base)
            ->assertOk();

        $this->assertSoftDeleted($product);
        $this->actingAsToken($manager)->getJson("{$base}")->assertOk();

        $restoreResponse = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/products/{$product->ulid}/restore");

        $restoreResponse->assertOk();
        $this->assertSame('draft', $product->fresh()->status->value);

        foreach (['product.published', 'product.unpublished', 'product.archived', 'product.restored'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'resource_id' => $product->id]);
        }
    }

    public function test_update_syncs_variants_transactionally(): void
    {
        $manager = $this->catalogManager();
        $product = Product::factory()->draft()->create();
        $kept = ProductVariant::factory()->forProduct($product)->withSku('KEEP-1')->create();
        $retired = ProductVariant::factory()->forProduct($product)->withSku('OLD-1')->create();

        $response = $this->actingAsToken($manager)
            ->patchJson("/api/v1/admin/products/{$product->ulid}", [
                'short_description' => 'Updated copy',
                'variants' => [
                    ['sku' => 'KEEP-1', 'cost_amount_minor' => 99900, 'cost_currency_code' => 'PHP'],
                    ['sku' => 'NEW-1'],
                ],
            ]);

        $response->assertOk();

        $this->assertSame('Updated copy', $product->fresh()->short_description);
        $this->assertSame(99900, $kept->fresh()->cost_amount_minor);
        $this->assertSoftDeleted($retired);
        $this->assertDatabaseHas('product_variants', ['sku' => 'NEW-1', 'deleted_at' => null]);
    }

    public function test_permission_matrix_blocks_unprivileged_users(): void
    {
        $customer = $this->plainStaffUser();
        $product = Product::factory()->create();
        $base = '/api/v1/admin/products';

        foreach ([
            ['GET', $base],
            ['POST', $base],
            ['GET', "{$base}/{$product->ulid}"],
            ['PATCH', "{$base}/{$product->ulid}"],
            ['DELETE', "{$base}/{$product->ulid}"],
            ['POST', "{$base}/{$product->ulid}/publish"],
        ] as [$method, $uri]) {
            $response = $this->actingAsToken($customer)->json($method, $uri);

            $response->assertStatus(403);
            $response->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }
}
