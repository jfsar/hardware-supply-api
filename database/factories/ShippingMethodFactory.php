<?php

namespace Database\Factories;

use App\Enums\MethodType;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('method-####'),
            'name' => fake()->words(3, true),
            'method_type' => MethodType::OwnDelivery,
            'provider' => null,
            'is_pickup' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function ownDelivery(): static
    {
        return $this->state(fn () => [
            'code' => 'own_delivery',
            'name' => 'Own Delivery',
            'method_type' => MethodType::OwnDelivery,
            'is_pickup' => false,
        ]);
    }

    public function courier(): static
    {
        return $this->state(fn () => [
            'code' => 'standard_courier',
            'name' => 'Standard Courier',
            'method_type' => MethodType::Courier,
            'provider' => 'Third Party',
            'is_pickup' => false,
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn () => [
            'code' => 'pickup',
            'name' => 'Store Pickup',
            'method_type' => MethodType::Pickup,
            'is_pickup' => true,
        ]);
    }
}
