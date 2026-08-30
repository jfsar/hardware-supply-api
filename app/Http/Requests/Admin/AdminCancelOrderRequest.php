<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin order cancellation (Phase 8, FR-ADMIN-005). The admin reason is
 * mandatory — unlike customer cancellations, these always carry an
 * operator explanation on the history row.
 */
class AdminCancelOrderRequest extends FormRequest
{
    /**
     * Route middleware enforces orders.cancel.
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
