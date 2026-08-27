<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'order_id' => OrderFactory::new(),
            'shipping_method_id' => ShippingMethodFactory::new(),
            'pickup_location_id' => null,
            'delivery_driver_id' => null,
            'shipment_number' => fake()->unique()->numerify('SHP-#####'),
            'status' => ShipmentStatus::Pending,
            'tracking_number' => null,
            'carrier_name' => null,
            'estimated_delivery_at' => fake()->dateTimeBetween('+1 day', '+14 days'),
            'shipped_at' => null,
            'delivered_at' => null,
            'picked_up_at' => null,
            'delivery_address_snapshot' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
        ]);
    }

    public function withMethod(ShippingMethod $method): static
    {
        return $this->state(fn () => [
            'shipping_method_id' => $method->id,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now(),
            'tracking_number' => fake()->bothify('TRACK-????-####'),
            'carrier_name' => 'Sample Carrier',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => ShipmentStatus::Delivered,
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now(),
            'tracking_number' => fake()->bothify('TRACK-????-####'),
            'carrier_name' => 'Sample Carrier',
        ]);
    }
}
