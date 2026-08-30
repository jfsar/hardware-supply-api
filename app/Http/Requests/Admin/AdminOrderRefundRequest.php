<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin order-level refund (Phase 8, FR-ADMIN-005): mirrors the payment
 * refund request shape but binds at the order root so the action can
 * resolve the primary captured payment.
 */
class AdminOrderRefundRequest extends FormRequest
{
    /**
     * Route middleware enforces orders.refund.
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.item' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
