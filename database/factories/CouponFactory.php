<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('SAVE###')),
            'usage_limit' => null,
            'per_customer_limit' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    /**
     * Back the coupon with a promotion defining the discount math.
     */
    public function backedBy(Promotion $promotion): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Outside its validity window.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }
}
