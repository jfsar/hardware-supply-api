<?php

namespace App\Http\Requests\Checkout;

use App\Enums\MethodType;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Read-only checkout validation (Phase 6 Task 3). Accepts the selected
 * shipping method and pickup location so the totals pipeline resolves a
 * real quote (FR-CART-007); the address is mandatory for delivery methods
 * and optional for pickup.
 */
class ValidateCheckoutRequest extends FormRequest
{
    /**
     * Guests may validate their own cart.
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
