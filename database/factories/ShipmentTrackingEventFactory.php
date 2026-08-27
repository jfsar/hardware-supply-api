<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentTrackingEvent>
 */
class ShipmentTrackingEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => ShipmentFactory::new(),
            'status' => ShipmentStatus::Pending,
            'location_text' => null,
            'event_at' => now(),
            'description' => fake()->sentence(),
            'raw_payload' => null,
        ];
    }

    public function forShipment(Shipment $shipment): static
    {
        return $this->state(fn () => [
            'shipment_id' => $shipment->id,
        ]);
    }

    public function withStatus(ShipmentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }
}
