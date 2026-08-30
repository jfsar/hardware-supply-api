<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a webhook endpoint (FR-NOTIF-003). Only HTTPS URLs are
 * accepted and the HMAC secret is generated server-side, returned once.
 */
class StoreWebhookEndpointRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'url', 'starts_with:https://'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
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
