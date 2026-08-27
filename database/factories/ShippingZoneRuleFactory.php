<?php

namespace Database\Factories;

use App\Models\ShippingZoneRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZoneRule>
 */
class ShippingZoneRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipping_zone_id' => ShippingZoneFactory::new(),
            'country_id' => null,
            'region_id' => null,
            'province_id' => null,
            'city_id' => null,
            'barangay_id' => null,
        ];
    }

    public function nationwide(): static
    {
        return $this->state(fn () => [
            'country_id' => null,
            'region_id' => null,
            'province_id' => null,
            'city_id' => null,
            'barangay_id' => null,
        ]);
    }
}
