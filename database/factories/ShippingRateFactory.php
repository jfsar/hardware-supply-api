<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipping_method_id' => ShippingMethodFactory::new(),
            'shipping_zone_id' => ShippingZoneFactory::new(),
            'min_weight_grams' => null,
            'max_weight_grams' => null,
            'min_length_mm' => null,
            'max_length_mm' => null,
            'min_order_total_minor' => null,
            'max_order_total_minor' => null,
            'rate_minor' => fake()->numberBetween(100, 5000),
            'currency_code' => config('commerce.currency', 'PHP'),
            'free_shipping_threshold_minor' => null,
            'estimated_min_days' => 1,
            'estimated_max_days' => 5,
            'starts_at' => now()->subYear(),
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function forMethod(ShippingMethod $method): static
    {
        return $this->state(fn () => [
            'shipping_method_id' => $method->id,
        ]);
    }

    public function forZone(ShippingZone $zone): static
    {
        return $this->state(fn () => [
            'shipping_zone_id' => $zone->id,
        ]);
    }

    public function withWeightRange(?int $min, ?int $max): static
    {
        return $this->state(fn () => [
            'min_weight_grams' => $min,
            'max_weight_grams' => $max,
        ]);
    }

    public function freeShipping(int $thresholdMinor): static
    {
        return $this->state(fn () => [
            'free_shipping_threshold_minor' => $thresholdMinor,
        ]);
    }
}
