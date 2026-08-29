<?php

namespace App\Http\Requests\NotificationPreferences;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Only authenticated users manage their own preferences.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_updates' => ['sometimes', 'boolean'],
            'payment_updates' => ['sometimes', 'boolean'],
            'promotions' => ['sometimes', 'boolean'],
            'back_in_stock' => ['sometimes', 'boolean'],
            'price_drop' => ['sometimes', 'boolean'],
        ];
    }
}
