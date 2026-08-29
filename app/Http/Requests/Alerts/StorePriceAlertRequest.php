<?php

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceAlertRequest extends FormRequest
{
    /**
     * Guests may subscribe with just an email (FR-NOTIF-002).
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'target_price_minor' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
