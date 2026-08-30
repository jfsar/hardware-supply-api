<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One moderation action (approve | reject | hide) taken against a
 * review. The transition is validated by the action's state machine;
 * no body is required.
 */
class ModerateReviewRequest extends FormRequest
{
    /**
     * Route middleware enforces products.update.
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
