<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin refund submission input (Phase 5 Task 5).
 */
class CreateRefundRequest extends FormRequest
{
    /**
     * Authorization runs through the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:'.implode(',', array_keys((array) config('payments.refunds.reasons')))],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
