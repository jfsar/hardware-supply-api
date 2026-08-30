<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a webhook endpoint (FR-NOTIF-003): profile fields, the active
 * toggle, and the subscribed event set (replaced wholesale on save). The
 * HMAC secret is never surfaced again after creation.
 */
class UpdateWebhookEndpointRequest extends FormRequest
{
    /**
     * Route middleware enforces webhooks.manage.
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
            'name' => ['sometimes', 'string', 'max:150'],
            'url' => ['sometimes', 'string', 'url', 'starts_with:https://'],
            'is_active' => ['sometimes', 'boolean'],
            'events' => ['sometimes', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'string', 'distinct', Rule::in((array) config('webhooks.events', []))],
        ];
    }

    /**
     * Human-readable attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'events.*' => 'event',
        ];
    }
}
