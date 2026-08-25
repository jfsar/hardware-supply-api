<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Models\VariantAttributeValue;
use App\Services\GenerateUniqueSlug;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(
        protected GenerateUniqueSlug $generateUniqueSlug,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Create a draft product with its variants and typed specifications in one
     * transaction (SRS principle 12).
     *
     * @param  array<string, mixed>  $data  validated request payload
     */
    public function __invoke(User $actor, array $data): Product
    {
        $product = DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                'category_id' => (int) $data['category_id'],
                'brand_id' => isset($data['brand_id']) ? (int) $data['brand_id'] : null,
                'name' => (string) $data['name'],
                'slug' => ($this->generateUniqueSlug)('products', (string) $data['name']),
                'sku_prefix' => $data['sku_prefix'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'warranty_type' => $data['warranty_type'] ?? null,
                'warranty_duration_months' => $data['warranty_duration_months'] ?? null,
                'status' => ProductStatus::Draft->value,
            ]);

            foreach ((array) ($data['attributes'] ?? []) as $entry) {
                $this->writeSpecPivot(
                    ProductAttributeValue::class,
                    ['product_id' => $product->id],
                    $entry,
                );
            }

            /** @var list<array<string, mixed>> $variantPayloads */
            $variantPayloads = array_values((array) $data['variants']);
            $defaultIndex = $this->resolveDefaultIndex($variantPayloads);

            foreach ($variantPayloads as $index => $payload) {
                $variant = $product->variants()->create([
                    'sku' => (string) $payload['sku'],
                    'name' => $payload['name'] ?? null,
                    'tax_class_id' => isset($payload['tax_class_id']) ? (int) $payload['tax_class_id'] : null,
                    'cost_amount_minor' => isset($payload['cost_amount_minor']) ? (int) $payload['cost_amount_minor'] : null,
                    'cost_currency_code' => $payload['cost_currency_code'] ?? null,
                    'weight_grams' => $payload['weight_grams'] ?? null,
                    'length_mm' => $payload['length_mm'] ?? null,
                    'width_mm' => $payload['width_mm'] ?? null,
                    'height_mm' => $payload['height_mm'] ?? null,
                    'is_default' => $index === $defaultIndex,
                    'status' => $payload['status'] ?? VariantStatus::Active->value,
                ]);

                foreach ((array) ($payload['attributes'] ?? []) as $entry) {
                    $this->writeSpecPivot(
                        VariantAttributeValue::class,
                        ['product_variant_id' => $variant->id],
                        $entry,
                    );
                }
            }

            return $product;
        });

        $this->recordAuditLog->model($actor, 'product.created', $product);

        return $product;
    }

    /**
     * Exactly one default variant: honour the first explicit flag, else the first variant.
     *
     * @param  list<array<string, mixed>>  $variantPayloads
     */
    private function resolveDefaultIndex(array $variantPayloads): int
    {
        foreach ($variantPayloads as $index => $payload) {
            if (($payload['is_default'] ?? false) === true) {
                return (int) $index;
            }
        }

        return 0;
    }

    /**
     * Persist one typed specification pivot row.
     *
     * @param  array<string, int>  $ownerColumns
     * @param  array<string, mixed>  $entry
     */
    private function writeSpecPivot(string $pivotClass, array $ownerColumns, array $entry): void
    {
        $attribute = Attribute::query()->findOrFail((int) $entry['attribute_id']);

        /** @var ProductAttributeValue|VariantAttributeValue $pivot */
        $pivot = new $pivotClass($ownerColumns + ['attribute_id' => $attribute->id]);

        $attribute->data_type->apply($pivot, $entry['value']);

        $pivot->save();
    }
}
