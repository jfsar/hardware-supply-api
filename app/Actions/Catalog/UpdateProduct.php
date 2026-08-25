<?php

namespace App\Actions\Catalog;

use App\Enums\VariantStatus;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttributeValue;
use App\Services\GenerateUniqueSlug;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    public function __construct(
        protected GenerateUniqueSlug $generateUniqueSlug,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Apply a partial update to a product. The variants array, when present,
     * carries the full desired variant set: matching SKUs are updated, new
     * SKUs created, and missing SKUs soft-deleted inside one transaction.
     *
     * @param  array<string, mixed>  $data  validated request payload
     */
    public function __invoke(User $actor, Product $product, array $data): Product
    {
        DB::transaction(function () use (&$product, $data): void {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $scalarFields = [
                'name', 'category_id', 'brand_id', 'sku_prefix', 'short_description',
                'description', 'warranty_type', 'warranty_duration_months',
            ];

            foreach ($scalarFields as $field) {
                if (array_key_exists($field, $data)) {
                    $product->{$field} = $data[$field];
                }
            }

            if ($product->isDirty('name')) {
                $product->slug = ($this->generateUniqueSlug)('products', (string) $product->name, $product->id);
            }

            $product->save();

            if (array_key_exists('attributes', $data)) {
                $product->attributeValues()->delete();

                foreach ((array) $data['attributes'] as $entry) {
                    $this->writeSpecPivot(['product_id' => $product->id], $entry);
                }
            }

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, (array) $data['variants']);
            }
        });

        $this->recordAuditLog->model($actor, 'product.updated', $product->refresh());

        return $product->refresh();
    }

    /**
     * Upsert the incoming variant payloads and retire absent SKUs.
     *
     * @param  list<array<string, mixed>>  $payloads
     */
    private function syncVariants(Product $product, array $payloads): void
    {
        $payloads = array_values($payloads);

        $incomingSkus = array_map(
            static fn (array $payload): string => strtolower((string) $payload['sku']),
            $payloads,
        );

        // Soft-delete stored variants that are no longer part of the payload.
        ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNotIn(
                DB::raw('LOWER(sku)'),
                $incomingSkus === [] ? [''] : $incomingSkus,
            )
            ->each(fn (ProductVariant $variant) => $variant->delete());

        $defaultIndex = $this->resolveDefaultIndex($payloads);

        foreach ($payloads as $index => $payload) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::withTrashed()
                ->where('product_id', $product->id)
                ->whereRaw('LOWER(sku) = ?', [strtolower((string) $payload['sku'])])
                ->first();

            $fields = [
                'name' => $payload['name'] ?? null,
                'tax_class_id' => isset($payload['tax_class_id']) ? (int) $payload['tax_class_id'] : null,
                'cost_amount_minor' => isset($payload['cost_amount_minor']) ? (int) $payload['cost_amount_minor'] : null,
                'cost_currency_code' => $payload['cost_currency_code'] ?? null,
                'weight_grams' => $payload['weight_grams'] ?? null,
                'length_mm' => $payload['length_mm'] ?? null,
                'width_mm' => $payload['width_mm'] ?? null,
                'height_mm' => $payload['height_mm'] ?? null,
                'status' => $payload['status'] ?? VariantStatus::Active->value,
                'deleted_at' => null,
            ];

            if ($variant === null) {
                $variant = new ProductVariant([
                    'product_id' => $product->id,
                    'sku' => (string) $payload['sku'],
                ] + $fields);
            } else {
                $variant->fill($fields);
            }

            $variant->is_default = $index === $defaultIndex;
            $variant->save();

            if (array_key_exists('attributes', $payload)) {
                VariantAttributeValue::query()
                    ->where('product_variant_id', $variant->id)
                    ->delete();

                foreach ((array) $payload['attributes'] as $entry) {
                    $this->writeSpecPivot(['product_variant_id' => $variant->id], $entry);
                }
            }
        }
    }

    /**
     * Exactly one default variant: honour the first explicit flag, else the first variant.
     *
     * @param  list<array<string, mixed>>  $payloads
     */
    private function resolveDefaultIndex(array $payloads): int
    {
        foreach ($payloads as $index => $payload) {
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
    private function writeSpecPivot(array $ownerColumns, array $entry): void
    {
        $attribute = Attribute::query()->findOrFail((int) $entry['attribute_id']);

        $pivot = str_starts_with((string) array_key_first($ownerColumns), 'product_variant')
            ? new VariantAttributeValue($ownerColumns + ['attribute_id' => $attribute->id])
            : new ProductAttributeValue($ownerColumns + ['attribute_id' => $attribute->id]);

        $attribute->data_type->apply($pivot, $entry['value']);

        $pivot->save();
    }
}
