<?php

namespace Database\Factories;

use App\Models\PickupLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PickupLocation>
 */
class PickupLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'code' => fake()->unique()->bothify('PICKUP-####'),
            'name' => fake()->company(),
            'country_id' => null,
            'region_id' => null,
            'province_id' => null,
            'city_id' => null,
            'barangay_id' => null,
            'postal_code_id' => null,
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'contact_phone' => fake()->phoneNumber(),
            'opening_hours' => null,
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
