<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\ReserveStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function productWithVariant(): array
    {
        $product = Product::factory()->active()->has(ProductVariant::factory(), 'variants')->create();
        $variant = $product->variants()->firstOrFail();
        $location = Location::factory()->primary()->create();

        // No warehouse existed at variant creation, so provision the row here.
        Inventory::factory()->forVariant($variant, $location)->withOnHand(6.0)->create();

        return [$product, $variant, $location];
    }

    public function test_detail_reports_availability_from_sellable_stock(): void
    {
        [$product] = $this->productWithVariant();

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $this->assertTrue($response->json('data.variants.0.availability'));
    }

    public function test_fully_reserved_variants_report_unavailable(): void
    {
        [$product, $variant, $location] = $this->productWithVariant();

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 6.0]], $location->id);
        });

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $this->assertFalse($response->json('data.variants.0.availability'));
    }

    public function test_public_payload_never_exposes_raw_stock_counts(): void
    {
        [$product] = $this->productWithVariant();

        $payload = $this->getJson("/api/v1/products/{$product->slug}")->json('data.variants.0');

        foreach (['quantity_on_hand', 'quantity_reserved', 'available_quantity', 'stock'] as $leak) {
            $this->assertArrayNotHasKey($leak, $payload);
        }
    }

    public function test_in_stock_filter_excludes_products_without_sellable_stock(): void
    {
        [$inStockProduct, $variant, $location] = $this->productWithVariant();

        $reservedProduct = Product::factory()
            ->active()
            ->has(ProductVariant::factory(), 'variants')
            ->create(['name' => 'Reserved Only', 'slug' => 'reserved-only']);
        $reservedVariant = $reservedProduct->variants()->firstOrFail();

        // The warehouse exists, so the observer already provisioned this row.
        Inventory::query()->where('product_variant_id', $reservedVariant->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => 2.0])->save();

        DB::transaction(function () use ($reservedVariant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $reservedVariant->id, 'quantity' => 2.0]], $location->id);
        });

        $response = $this->getJson('/api/v1/products?in_stock=1&per_page=50');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains($inStockProduct->slug, $slugs);
        $this->assertNotContains('reserved-only', $slugs);
    }
}
