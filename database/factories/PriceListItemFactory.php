<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListItem>
 */
class PriceListItemFactory extends Factory
{
    /**
     * Money in integer minor units; window opens now by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceListFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'price_amount_minor' => 25000,
            'currency_code' => config('commerce.currency', 'PHP'),
            'effective_from' => now()->subHour(),
            'effective_to' => null,
        ];
    }

    /**
     * Bind the item to a concrete list and variant.
     */
    public function forPricing(PriceList $priceList, ProductVariant $variant, int $amountMinor): static
    {
        return $this->state(fn (array $attributes) => [
            'price_list_id' => $priceList->id,
            'product_variant_id' => $variant->id,
            'price_amount_minor' => $amountMinor,
            'currency_code' => $priceList->currency_code,
        ]);
    }

    /**
     * A closed historical price window.
     */
    public function endedAt(\DateTimeInterface $end): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => (clone $end)->modify('-1 hour'),
            'effective_to' => $end,
        ]);
    }
}
