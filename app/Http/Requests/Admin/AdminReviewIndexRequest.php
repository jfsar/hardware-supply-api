<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlisted filters for the admin review moderation queue.
 */
class AdminReviewIndexRequest extends FormRequest
{
    /**
     * Route middleware enforces products.view.
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
            'status' => ['nullable', 'string', Rule::in(array_map(
                fn (ReviewStatus $status): string => $status->value,
                ReviewStatus::cases()
            ))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('reports.per_page', 100)],
        ];
    }
}
