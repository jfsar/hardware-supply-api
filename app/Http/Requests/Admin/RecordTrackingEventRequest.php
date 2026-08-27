<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin tracking-event input (Phase 6 Task 4). Events are append-only;
 * this request describes the newest scan/transition for a shipment.
 */
class RecordTrackingEventRequest extends FormRequest
{
    /**
     * Only admins with the orders.fulfill permission reach this.
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
            'status' => ['required', 'string', Rule::in(array_map(
                fn (ShipmentStatus $status): string => $status->value,
                ShipmentStatus::cases()
            ))],
            'location_text' => ['nullable', 'string', 'max:255'],
            'event_at' => ['nullable', 'date', 'after_or_equal:2000-01-01'],
            'description' => ['nullable', 'string', 'max:500'],
            'raw_payload' => ['nullable', 'array'],
        ];
    }
}
