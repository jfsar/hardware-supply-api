<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Read-only checkout pre-validation; no body fields required today.
 * Kept as a FormRequest per house convention and future extension.
 */
class ValidateCheckoutRequest extends FormRequest
{
    /**
     * Guests may validate their own cart.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
