<?php

namespace Database\Factories;

use App\Enums\AlertSubscriptionStatus;
use App\Models\BackInStockSubscription;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackInStockSubscription>
 */
class BackInStockSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => strtolower(fake()->safeEmail()),
            'product_variant_id' => ProductVariantFactory::new(),
            'status' => AlertSubscriptionStatus::Active,
            'notified_at' => null,
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => ['product_variant_id' => $variant->id]);
    }

    public function forUser(?User $user): static
    {
        return $this->state(fn () => ['user_id' => $user?->id]);
    }
}
