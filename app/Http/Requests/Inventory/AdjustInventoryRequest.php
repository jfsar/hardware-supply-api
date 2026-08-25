<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtolower(trim((string) $this->input('type'))),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    /**
     * Validation rules (FR-INV-005): a stock movement type, a signed delta
     * with magnitude greater than zero, and an audit reason.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', $this->adjustableTypes())],
            'quantity_delta' => [
                'required',
                'numeric',
                'not_in:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (abs((float) $value) < 0.001) {
                        $fail(__('The quantity delta must have a magnitude greater than zero.'));
                    }
                },
            ],
            'reason' => ['required', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'exists:locations,ulid'],
        ];
    }

    /**
     * The validated movement type.
     */
    public function movementType(): MovementType
    {
        return MovementType::from((string) $this->validated('type'));
    }

    /**
     * The signed adjustment magnitude.
     */
    public function quantityDelta(): float
    {
        return (float) $this->validated('quantity_delta');
    }

    /**
     * Movement types an administrator may apply directly.
     *
     * @return list<string>
     */
    private function adjustableTypes(): array
    {
        return [
            MovementType::Purchase->value,
            MovementType::Return->value,
            MovementType::Adjustment->value,
            MovementType::Damage->value,
            MovementType::Loss->value,
        ];
    }
}
