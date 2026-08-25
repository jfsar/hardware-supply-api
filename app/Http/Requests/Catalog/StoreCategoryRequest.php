<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Normalize the slug-able name before validation. Only fields present in
     * the payload are merged so partial updates keep other fields intact.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'seo_title', 'seo_description'] as $field) {
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
            'parent_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $parent = Category::query()->find($value);

                    if ($parent === null || $parent->deleted_at !== null) {
                        $fail(__('The selected parent category is invalid.'));
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:-2147483648', 'max:2147483647'],
            'status' => ['in:active,inactive'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
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
