<?php

namespace App\Http\Requests\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlisted admin order listing filters (FR-ADMIN-004).
 */
class AdminOrderIndexRequest extends FormRequest
{
    /**
     * Route middleware enforces orders.view.
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
            'order_status' => ['nullable', 'string', Rule::in(array_map(
                fn (OrderStatus $status): string => $status->value,
                OrderStatus::cases()
            ))],
            'payment_status' => ['nullable', 'string', Rule::in(array_map(
                fn (PaymentStatus $status): string => $status->value,
                PaymentStatus::cases()
            ))],
            'fulfillment_status' => ['nullable', 'string', Rule::in(array_map(
                fn (FulfillmentStatus $status): string => $status->value,
                FulfillmentStatus::cases()
            ))],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'placed_at', 'total_minor', 'order_number'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('reports.per_page', 100)],
        ];
    }
}
