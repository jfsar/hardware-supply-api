<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlisted query params for the admin customer list (FR-ADMIN-001).
 */
class AdminCustomerIndexRequest extends FormRequest
{
    /**
     * Route middleware enforces customers.view.
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_map(
                fn (UserStatus $status): string => $status->value,
                UserStatus::cases()
            ))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('reports.per_page', 100)],
        ];
    }
}
