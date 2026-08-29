<?php

namespace App\Http\Requests\Recommendations;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecommendationClickRequest extends FormRequest
{
    /**
     * Click tracking is open to guests and signed-in shoppers alike.
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
