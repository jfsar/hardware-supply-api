<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Money is stored in integer minor units with an ISO currency code.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'product_id' => ProductFactory::new(),
            'tax_class_id' => null,
            'sku' => strtoupper(fake()->unique()->bothify('??-#####')),
            'name' => fake()->optional()->word(),
            'cost_amount_minor' => 125000,
            'cost_currency_code' => 'PHP',
            'weight_grams' => fake()->numberBetween(100, 5000),
            'is_default' => true,
            'status' => 'active',
        ];
    }

    /**
     * Indicate this is a non-default variant.
     */
    public function notDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => false,
        ]);
    }

    /**
     * Attach the variant to a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Override the SKU for deterministic uniqueness assertions.
     */
    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes) => [
            'sku' => $sku,
        ]);
    }
}
