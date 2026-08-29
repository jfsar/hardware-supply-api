<?php

namespace App\Http\Requests\Comparison;

use Illuminate\Foundation\Http\FormRequest;

class StoreComparisonItemRequest extends FormRequest
{
    /**
     * Guests may compare too; the request needs no authenticated user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_ulid' => ['required', 'string', 'ulid'],
        ];
    }
}
