<?php

namespace App\Http\Requests\Catalog\Concerns;

use App\Enums\AttributeDataType;
use App\Models\Attribute;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Validation\ValidationException;

trait ValidatesProductPayload
{
    /**
     * Top-level fields the catalog accepts (FR-CAT-005/006/007 exclusions).
     *
     * @return list<string>
     */
    private function allowedProductFields(): array
    {
        return [
            'name', 'category_id', 'brand_id', 'sku_prefix', 'short_description',
            'description', 'warranty_type', 'warranty_duration_months',
            'attributes', 'variants',
        ];
    }

    /**
     * Fields accepted on each nested variant.
     *
     * @return list<string>
     */
    private function allowedVariantFields(): array
    {
        return [
            'sku', 'name', 'tax_class_id', 'cost_amount_minor', 'cost_currency_code',
            'weight_grams', 'length_mm', 'width_mm', 'height_mm',
            'is_default', 'status', 'attributes',
        ];
    }

    /**
     * Reject any payload key outside the strict allowlist.
     */
    private function rejectUnknownFields(): void
    {
        $errors = [];

        foreach (array_diff($this->keys(), $this->allowedProductFields()) as $field) {
            $errors[$field] = [__('The :field field is not accepted.', ['field' => $field])];
        }

        foreach ((array) $this->input('variants', []) as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            foreach (array_diff(array_keys($variant), $this->allowedVariantFields()) as $field) {
                $errors["variants.$index.$field"] = [__('The :field field is not accepted.', ['field' => $field])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate a spec entry's value against its attribute data type (FR-CAT-008).
     */
    private function validateSpecValue(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var array<string, mixed> $data */
            $data = $this->validationData();

            if (preg_match('/^variants\.(\d+)\.attributes\.(\d+)\.value$/', $attribute, $m) === 1) {
                $entry = $data['variants'][(int) $m[1]]['attributes'][(int) $m[2]] ?? null;
            } elseif (preg_match('/^attributes\.(\d+)\.value$/', $attribute, $m) === 1) {
                $entry = $data['attributes'][(int) $m[1]] ?? null;
            } else {
                $entry = null;
            }

            $attributeId = is_array($entry) ? ($entry['attribute_id'] ?? null) : null;

            $definition = Attribute::query()->find($attributeId);

            if ($definition === null) {
                $fail(__('The selected attribute definition is invalid.'));

                return;
            }

            $valid = match ($definition->data_type) {
                AttributeDataType::Text => is_string($value),
                AttributeDataType::Integer => is_int($value),
                AttributeDataType::Decimal => is_numeric($value) && ! is_bool($value),
                AttributeDataType::Boolean => is_bool($value),
                AttributeDataType::Option => $definition->values()
                    ->where('value_text', (string) $value)
                    ->exists(),
            };

            if (! $valid) {
                $fail(__('The :attribute value must be a valid :type.', [
                    'type' => $definition->data_type->value,
                ]));
            }
        };
    }

    /**
     * Ensure SKUs are unique case-insensitively within the payload (FR-CAT-004).
     */
    private function uniqueSkuInPayload(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var list<array<string, mixed>> $variants */
            $variants = (array) $this->input('variants', []);

            $skus = array_map(
                static fn (array $variant): string => strtolower((string) ($variant['sku'] ?? '')),
                array_filter($variants, 'is_array'),
            );

            if (count($skus) !== count(array_unique($skus))) {
                $fail(__('Each variant must have a unique SKU.'));
            }
        };
    }

    /**
     * Ensure SKUs are unique across stored variants, ignoring this product's own rows on update.
     */
    private function uniqueSkuInStorage(?int $ignoreProductId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreProductId): void {
            $taken = ProductVariant::withTrashed()
                ->whereRaw('LOWER(sku) = ?', [strtolower((string) $value)])
                ->when($ignoreProductId !== null, fn ($query) => $query->where('product_id', '!=', $ignoreProductId))
                ->exists();

            if ($taken) {
                $fail(__('The SKU :sku has already been taken.', ['sku' => $value]));
            }
        };
    }
}
