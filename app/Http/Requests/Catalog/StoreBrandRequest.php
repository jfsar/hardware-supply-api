<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    /**
     * Normalize the slug-able name before validation. Only fields present in
     * the payload are merged so partial updates keep other fields intact.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'description'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = trim((string) $this->input($field));
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['in:active,inactive'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
