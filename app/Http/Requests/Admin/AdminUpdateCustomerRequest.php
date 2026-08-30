<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Profile edits an admin may make on a customer (FR-ADMIN-002): names
 * and phone only. At least one field must change.
 */
class AdminUpdateCustomerRequest extends FormRequest
{
    /**
     * Route middleware enforces customers.update.
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
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Require at least one editable field to be present.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasAny(['first_name', 'last_name', 'phone'])) {
                $validator->errors()->add('first_name', __('At least one field must be provided.'));
            }
        });
    }
}
