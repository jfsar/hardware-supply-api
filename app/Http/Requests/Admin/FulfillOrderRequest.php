<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin fulfillment input (Phase 6 Task 4): a map of order_item_id =>
 * quantity to allocate into one new shipment. Optional carrier/driver
 * details decorate the shipment header.
 */
class FulfillOrderRequest extends FormRequest
{
    /**
     * Only admins with the orders.fulfill permission reach this.
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
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'numeric', 'gt:0'],

            'tracking_number' => ['nullable', 'string', 'max:100'],
            'carrier_name' => ['nullable', 'string', 'max:150'],
            'delivery_driver_id' => ['nullable', 'integer', Rule::exists('delivery_drivers', 'id')],
        ];
    }
}
