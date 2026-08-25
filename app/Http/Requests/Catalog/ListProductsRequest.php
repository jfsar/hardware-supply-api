<?php

namespace App\Http\Requests\Catalog;

use App\Services\Search\SortOrder;
use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    /**
     * Normalize filter input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim((string) $this->input('q', '')),
            'brands' => array_values(array_filter((array) $this->input('brands', []))),
        ]);
    }

    /**
     * Only allowlisted sort/filter fields are accepted.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brands' => ['nullable', 'array', 'max:10'],
            'brands.*' => ['string', 'max:255'],
            'min_price_minor' => ['nullable', 'integer', 'min:0'],
            'max_price_minor' => ['nullable', 'integer', 'min:0', 'gte:min_price_minor'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:'.implode(',', SortOrder::allowlist())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
