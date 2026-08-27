<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => ShipmentFactory::new(),
            'order_item_id' => OrderItemFactory::new(),
            'quantity' => fake()->randomFloat(3, 1, 10),
        ];
    }

    public function forShipment(Shipment $shipment): static
    {
        return $this->state(fn () => [
            'shipment_id' => $shipment->id,
        ]);
    }

    public function forOrderItem(OrderItem $orderItem): static
    {
        return $this->state(fn () => [
            'order_item_id' => $orderItem->id,
        ]);
    }

    public function withQuantity(float $quantity): static
    {
        return $this->state(fn () => [
            'quantity' => $quantity,
        ]);
    }
}
