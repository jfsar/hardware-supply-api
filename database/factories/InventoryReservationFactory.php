<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\InventoryReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReservation>
 */
class InventoryReservationFactory extends Factory
{
    /**
     * Define the model's default state: an active reservation expiring in 15 minutes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => LocationFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'cart_id' => null,
            'order_id' => null,
            'quantity' => 1.0,
            'status' => 'active',
            'expires_at' => now()->addMinutes(15),
            'released_at' => null,
            'consumed_at' => null,
        ];
    }

    /**
     * Expire the reservation in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Mark the reservation already terminal.
     */
    public function status(string|ReservationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status instanceof ReservationStatus ? $status->value : $status,
        ]);
    }
}
