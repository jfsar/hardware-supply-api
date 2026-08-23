<?php

namespace App\Http\Resources;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class AddressResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'region' => $this->whenLoaded('region', fn () => [
                'code' => $this->region->code,
                'name' => $this->region->name,
            ]),
            'province' => $this->whenLoaded('province', fn () => $this->province !== null ? [
                'code' => $this->province->code,
                'name' => $this->province->name,
            ] : null),
            'city' => $this->whenLoaded('city', fn () => [
                'code' => $this->city->code,
                'name' => $this->city->name,
                'type' => $this->city->city_type,
            ]),
            'barangay' => $this->whenLoaded('barangay', fn () => [
                'code' => $this->barangay->code,
                'name' => $this->barangay->name,
            ]),
            'postal_code' => $this->whenLoaded('postalCode', fn () => $this->postalCode !== null
                ? $this->postalCode->code
                : null),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
        ];
    }
}
