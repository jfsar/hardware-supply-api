<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'ulid' => (string) Str::ulid(),
            'category_id' => CategoryFactory::new(),
            'brand_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sku_prefix' => strtoupper(Str::substr(Str::slug($name, ''), 0, 5)),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'warranty_type' => fake()->randomElement(['none', 'store', 'manufacturer']),
            'warranty_duration_months' => fake()->randomElement([6, 12, 24]),
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Indicate the product is publicly visible.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate the product is still a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate the product has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Archived,
            'published_at' => null,
        ]);
    }

    /**
     * Attach a brand and category explicitly.
     */
    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
