<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'name' => $this->faker->words(3, true),
            'code' => null,
            'promotion_type' => 'percentage',
            'discount_type' => 'percentage',
            'discount_value' => 10.000,
            'max_discount_amount_minor' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => null,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'is_stackable' => true,
            'priority' => 0,
            'status' => 'active',
        ];
    }

    /**
     * Percentage discount promotions.
     */
    public function percentage(float $percent, ?int $capMinor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_type' => 'percentage',
            'discount_type' => 'percentage',
            'discount_value' => $percent,
            'max_discount_amount_minor' => $capMinor,
        ]);
    }

    /**
     * Fixed-amount-off promotions; the amount arrives in minor units and
     * is stored as major units on DECIMAL(18,3).
     */
    public function fixedAmount(int $amountMinor): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_type' => 'fixed_amount',
            'discount_type' => 'fixed_amount',
            'discount_value' => $amountMinor / 100,
        ]);
    }

    /**
     * Time-boxed flash sales.
     */
    public function flashSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_type' => 'flash_sale',
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * Free-shipping flag promotions.
     */
    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_type' => 'free_shipping',
            'discount_type' => 'free_shipping',
        ]);
    }

    /**
     * Outside its active window.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }
}
