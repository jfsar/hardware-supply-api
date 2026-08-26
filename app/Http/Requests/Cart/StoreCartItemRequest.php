<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    /**
     * Guests may add to their own cart.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'variant' => trim((string) $this->input('variant')),
            'quantity' => $this->input('quantity'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'variant' => ['required', 'string', Rule::exists('product_variants', 'ulid')->whereNull('deleted_at')],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
        ];
    }
}
