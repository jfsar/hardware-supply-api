<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Restore a previously suspended customer (FR-ADMIN-003). Route
 * middleware enforces customers.suspend.
 */
class AdminRestoreCustomerRequest extends FormRequest
{
    /**
     * Route middleware enforces customers.suspend.
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
        return [];
    }
}
