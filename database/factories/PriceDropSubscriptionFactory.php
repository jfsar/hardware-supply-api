<?php

namespace Database\Factories;

use App\Enums\AlertSubscriptionStatus;
use App\Models\PriceDropSubscription;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceDropSubscription>
 */
class PriceDropSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => strtolower(fake()->safeEmail()),
            'product_variant_id' => ProductVariantFactory::new(),
            'target_price_minor' => null,
            'currency_code' => config('commerce.currency', 'PHP'),
            'status' => AlertSubscriptionStatus::Active,
            'notified_at' => null,
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => ['product_variant_id' => $variant->id]);
    }

    public function withTarget(int $targetPriceMinor): static
    {
        return $this->state(fn () => ['target_price_minor' => $targetPriceMinor]);
    }
}
