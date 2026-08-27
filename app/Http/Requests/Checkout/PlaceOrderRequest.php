<?php

namespace App\Http\Requests\Checkout;

use App\Enums\MethodType;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout placement input (Phase 4 Task 8; extended Phase 6 Task 3).
 * The cart itself carries the items; this request supplies payment
 * intent, guest contact details, the shipping method/pickup location,
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
        $hasMethod = is_string($this->input('shipping_method_code')) && $this->input('shipping_method_code') !== '';
        $isPickup = $hasMethod && $this->isPickupMethod();
        $geo = ['nullable', 'integer'];
        $addressRequired = $hasMethod && ! $isPickup ? 'required' : 'nullable';

        return [
            'payment_method' => ['required', 'string', Rule::in(['cod', 'card', 'e_wallet', 'qr', 'gateway'])],
            'contact_email' => [$this->isGuest() ? 'required' : 'nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],

            'shipping_method_code' => [
                'nullable', 'string', 'max:50',
                Rule::exists('shipping_methods', 'code')->where('is_active', true),
            ],
            'pickup_location_id' => [
                $isPickup ? 'required' : 'nullable',
                'integer',
                Rule::exists('pickup_locations', 'id')->where('is_active', true),
            ],

            'address.recipient_name' => [$addressRequired, 'string', 'max:200'],
            'address.recipient_phone' => [$addressRequired, 'string', 'max:30'],
            'address.address_line1' => [$addressRequired, 'string', 'max:255'],
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

    /**
     * Whether the chosen method is a store-pickup method. Unknown codes
     * are treated as delivery so the address stays required.
     */
    protected function isPickupMethod(): bool
    {
        $type = ShippingMethod::query()
            ->where('code', (string) $this->input('shipping_method_code'))
            ->value('method_type');

        return $type instanceof MethodType
            ? $type === MethodType::Pickup
            : $type === MethodType::Pickup->value;
    }
}
