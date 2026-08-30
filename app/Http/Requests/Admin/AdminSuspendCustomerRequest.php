<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suspend a customer (FR-ADMIN-003). No body is required; the route
 * middleware enforces customers.suspend and the action refuses
 * self-suspension.
 */
class AdminSuspendCustomerRequest extends FormRequest
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
