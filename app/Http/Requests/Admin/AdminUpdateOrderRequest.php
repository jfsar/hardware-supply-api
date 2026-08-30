<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin order edits (Phase 8, SRS §69): append-only manual adjustment
 * rows with allowlisted adjustment types. Rows are stored with signed
 * amounts; the totals invariant is re-verified by the action.
 */
class AdminUpdateOrderRequest extends FormRequest
{
    /**
     * Route middleware enforces orders.update.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string|list<mixed>>
     */
    public function rules(): array
    {
        return [
            'adjustments' => ['sometimes', 'required', 'array', 'min:1'],
            'adjustments.*.type' => ['required', 'string', Rule::in(['fee', 'charge', 'discount'])],
            'adjustments.*.label' => ['required', 'string', 'max:150'],
            'adjustments.*.amount_minor' => ['required', 'integer', 'not_in:0'],
            'adjustments.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
