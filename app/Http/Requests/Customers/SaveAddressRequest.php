<?php

namespace App\Http\Requests\Customers;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Http\FormRequest;

class SaveAddressRequest extends FormRequest
{
    /**
     * Normalize input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'address_line1' => trim((string) $this->input('address_line1')),
            'address_line2' => $this->filled('address_line2') ? trim((string) $this->input('address_line2')) : null,
            'recipient_name' => trim((string) $this->input('recipient_name')),
            'recipient_phone' => trim((string) $this->input('recipient_phone')),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'region_id' => [
                'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $region = Region::query()->find($value);

                    if ($region === null || ! $region->is_active || $region->country?->iso2 !== 'PH') {
                        $fail(__('The selected region is invalid.'));
                    }
                },
            ],
            'province_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $province = Province::query()->find($value);

                    if ($province === null || (int) $province->region_id !== (int) $this->input('region_id')) {
                        $fail(__('The selected province is invalid for the given region.'));
                    }
                },
            ],
            'city_id' => [
                'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $city = City::query()->find($value);

                    $matchesRegion = $city !== null && (int) $city->region_id === (int) $this->input('region_id');
                    $provinceGiven = $this->filled('province_id');
                    $matchesProvince = ((int) $city?->province_id) === ($provinceGiven ? (int) $this->input('province_id') : 0)
                        || (! $provinceGiven && $city?->province_id === null);

                    if (! $matchesRegion || ! $matchesProvince) {
                        $fail(__('The selected city or municipality is invalid for the given region and province.'));
                    }
                },
            ],
            'barangay_id' => [
                'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $barangay = Barangay::query()->find($value);

                    if ($barangay === null || (int) $barangay->city_id !== (int) $this->input('city_id')) {
                        $fail(__('The selected barangay is invalid for the given city.'));
                    }
                },
            ],
            'postal_code_id' => ['nullable', 'integer', 'exists:postal_codes,id'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
