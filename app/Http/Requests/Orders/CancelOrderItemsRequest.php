<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial cancellation of specific order lines (FR-ORD-004).
 */
class CancelOrderItemsRequest extends FormRequest
{
    /**
     * Only authenticated customers reach this endpoint.
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
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'integer', Rule::exists('order_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
        ];
    }
}
