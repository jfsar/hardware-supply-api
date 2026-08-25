<?php

namespace App\Http\Requests\Catalog;

use App\Enums\WarrantyType;
use App\Http\Requests\Catalog\Concerns\ValidatesProductPayload;
use App\Models\Brand;
use App\Models\Category;
use App\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ValidatesProductPayload;

    /**
     * Enforce the strict field allowlist before rule evaluation.
     */
    protected function prepareForValidation(): void
    {
        $this->rejectUnknownFields();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $category = Category::query()->find($value);

                    if ($category === null || $category->deleted_at !== null) {
                        $fail(__('The selected category is invalid.'));
                    }
                },
            ],
            'brand_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $brand = Brand::query()->find($value);

                    if ($brand === null || $brand->deleted_at !== null) {
                        $fail(__('The selected brand is invalid.'));
                    }
                },
            ],
            'sku_prefix' => ['nullable', 'string', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'warranty_type' => ['nullable', 'in:'.implode(',', array_column(WarrantyType::cases(), 'value'))],
            'warranty_duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'attributes' => ['nullable', 'array', 'max:50'],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
        ]
        + $this->specCollections()
        + $this->variantRules();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Spec-entry collections at product and variant level.
     *
     * @return array<string, list<mixed>>
     */
    private function specCollections(): array
    {
        return [
            'attributes.*.attribute_id' => ['required', 'integer'],
            'attributes.*.value' => ['present', $this->validateSpecValue()],
            'variants.*.attributes' => ['nullable', 'array', 'max:20'],
            'variants.*.attributes.*.attribute_id' => ['required', 'integer'],
            'variants.*.attributes.*.value' => ['present', $this->validateSpecValue()],
        ];
    }

    /**
     * Per-variant field rules.
     *
     * @return array<string, list<mixed>>
     */
    private function variantRules(): array
    {
        return [
            'variants.*.sku' => [
                'required', 'string', 'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                $this->uniqueSkuInPayload(),
                $this->uniqueSkuInStorage(),
            ],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.tax_class_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || TaxClass::query()->whereKey($value)->exists()) {
                        return;
                    }

                    $fail(__('The selected tax class is invalid.'));
                },
            ],
            'variants.*.cost_amount_minor' => ['nullable', 'integer', 'min:0'],
            'variants.*.cost_currency_code' => [
                'nullable',
                'required_with:variants.*.cost_amount_minor',
                'string', 'size:3',
            ],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:2_000_000'],
            'variants.*.length_mm' => ['nullable', 'integer', 'min:0', 'max:500_000'],
            'variants.*.width_mm' => ['nullable', 'integer', 'min:0', 'max:500_000'],
            'variants.*.height_mm' => ['nullable', 'integer', 'min:0', 'max:500_000'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.status' => ['in:active,inactive,archived'],
        ];
    }
}
