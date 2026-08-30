<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add an order note (Phase 8, FR-ADMIN-006). Customer-visible notes
 * surface on the customer's OrderResource.
 */
class AdminOrderNoteRequest extends FormRequest
{
    /**
     * Route middleware enforces orders.notes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
            'is_customer_visible' => ['sometimes', 'boolean'],
        ];
    }
}
