<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDocument>
 */
class ProductDocumentFactory extends Factory
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
            'title' => fake()->words(2, true),
            'storage_disk' => config('catalog.media_disk', 'public'),
            'path' => 'products/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'sort_order' => 0,
        ];
    }

    /**
     * Attach the document to a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
