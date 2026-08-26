<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout placement input (Phase 4 Task 8). The cart itself carries the
 * items; this request supplies payment intent, guest contact details,
 * and the shipping address snapshot.
 */
class PlaceOrderRequest extends FormRequest
{
    /**
     * Guests may check out with a cart token.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $geo = ['nullable', 'integer'];

        return [
            'payment_method' => ['required', 'string', Rule::in(['cod', 'card', 'e_wallet', 'qr', 'gateway'])],
            'contact_email' => [$this->isGuest() ? 'required' : 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],

            'address.recipient_name' => ['required', 'string', 'max:200'],
            'address.recipient_phone' => ['required', 'string', 'max:30'],
            'address.address_line1' => ['required', 'string', 'max:255'],
            'address.address_line2' => ['nullable', 'string', 'max:255'],
            'address.country_id' => [...$geo, 'exists:countries,id'],
            'address.region_id' => [...$geo, 'exists:regions,id'],
            'address.province_id' => [...$geo, 'exists:provinces,id'],
            'address.city_id' => [...$geo, 'exists:cities,id'],
            'address.barangay_id' => [...$geo, 'exists:barangays,id'],
            'address.postal_code_id' => [...$geo, 'exists:postal_codes,id'],
            'address.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Whether the caller checks out as a guest.
     */
    protected function isGuest(): bool
    {
        return $this->user() === null;
    }
}
