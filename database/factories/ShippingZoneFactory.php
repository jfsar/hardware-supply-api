<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('zone-####'),
            'is_active' => true,
        ];
    }

    public function nationwide(): static
    {
        return $this->state(fn () => [
            'code' => 'nationwide',
            'name' => 'Nationwide',
        ]);
    }
}
