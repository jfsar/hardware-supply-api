<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A minimal sellable catalog for local sandbox runs (Phase 5 webhook e2e):
 * one active product + variant above the PayRex ₱20 floor, priced on the
 * default list, stocked at MAIN-WH. Idempotent — safe to re-run.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TaxClassSeeder::class);

        $vatClass = TaxClass::query()->where('code', 'VAT-PH')->firstOrFail();

        $category = Category::query()->firstOrCreate(
            ['slug' => 'hand-tools'],
            [
                'ulid' => (string) Str::ulid(),
                'parent_id' => null,
                'name' => 'Hand Tools',
                'description' => 'Demo category for sandbox payments.',
                'sort_order' => 0,
                'status' => 'active',
            ],
        );

        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'demobrand'],
            [
                'ulid' => (string) Str::ulid(),
                'name' => 'DemoBrand',
                'description' => 'Demo brand for sandbox payments.',
                'status' => 'active',
            ],
        );

        $product = Product::query()->firstOrCreate(
            ['slug' => 'demo-claw-hammer'],
            [
                'ulid' => (string) Str::ulid(),
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => 'Demo Claw Hammer',
                'sku_prefix' => 'DEMOH',
                'short_description' => '16oz forged steel claw hammer.',
                'description' => 'A demo claw hammer used to exercise the full checkout → PayRex → webhook pipeline locally.',
                'warranty_type' => 'manufacturer',
                'warranty_duration_months' => 12,
                'status' => ProductStatus::Active,
                'published_at' => now(),
            ],
        );

        $variant = ProductVariant::query()->firstOrCreate(
            ['sku' => 'DEMO-HAMMER-16OZ'],
            [
                'ulid' => (string) Str::ulid(),
                'product_id' => $product->id,
                'tax_class_id' => $vatClass->id,
                'name' => '16 oz',
                'cost_amount_minor' => 15000,
                'cost_currency_code' => config('commerce.currency', 'PHP'),
                'weight_grams' => 800,
                'is_default' => true,
                'status' => 'active',
            ],
        );

        // The InventoryObserver already opened a zero row at MAIN-WH when
        // the variant was created; stock it to a sellable level.
        $location = Location::query()->where('code', 'MAIN-WH')->first();
        if ($location === null) {
            $this->call(LocationSeeder::class);
            $location = Location::query()->where('code', 'MAIN-WH')->firstOrFail();
        }

        Inventory::query()->updateOrCreate(
            ['location_id' => $location->id, 'product_variant_id' => $variant->id],
            ['quantity_on_hand' => 10.000, 'quantity_reserved' => 0.000, 'reorder_level' => 0.000],
        );

        $priceList = PriceList::query()->firstOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => 'Default Pricing',
                'currency_code' => config('commerce.currency', 'PHP'),
                'customer_scope' => 'all',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        PriceListItem::query()->firstOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_variant_id' => $variant->id,
                'effective_from' => now()->startOfDay(),
            ],
            [
                'price_amount_minor' => 25000,
                'currency_code' => config('commerce.currency', 'PHP'),
                'effective_to' => null,
            ],
        );

        $this->command?->info('Demo catalog ready: DEMO-HAMMER-16OZ @ ₱250.00, stock 10 @ MAIN-WH.');
    }
}
