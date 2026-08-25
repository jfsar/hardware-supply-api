<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => ProductFactory::new(),
            'product_variant_id' => null,
            'storage_disk' => config('catalog.media_disk', 'public'),
            'path' => 'products/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 900,
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    /**
     * Indicate this is the primary gallery image.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Attach the image to a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
