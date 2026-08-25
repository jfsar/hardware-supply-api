<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Catalog\Concerns\ValidatesProductPayload;
use App\Models\Product;

class UpdateProductRequest extends StoreProductRequest
{
    use ValidatesProductPayload;

    /**
     * On update every product-level field becomes optional and stored SKUs
     * of sibling products stay reserved.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => &$constraints) {
            array_unshift($constraints, 'sometimes');

            if ($field === 'variants') {
                $constraints = ['sometimes', 'array', 'min:1', 'max:100'];
            }
        }

        unset($constraints);

        /** @var Product|null $product */
        $product = $this->route('product');

        $ignoreProductId = $product?->id;

        $rules['variants.*.sku'] = [
            'sometimes', 'string', 'max:100',
            'regex:/^[A-Za-z0-9_-]+$/',
            $this->uniqueSkuInPayload(),
            $this->uniqueSkuInStorage($ignoreProductId),
        ];

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
