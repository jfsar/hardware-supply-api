<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'product_id' => ProductFactory::new(),
            'user_id' => UserFactory::new(),
            'order_item_id' => OrderItemFactory::new(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->words(4, true),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
            'verified_purchase' => false,
            'published_at' => null,
        ];
    }

    /**
     * A verified, delivered-purchase review backing the badge (FR-REV-003).
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_purchase' => true,
        ]);
    }

    /**
     * An approved review visible on the public page.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Attach the review to a concrete product, author, and proof line.
     */
    public function forProductAndUser(Product $product, User $user, ?OrderItem $orderItem = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_item_id' => $orderItem?->id ?? OrderItemFactory::new(),
        ]);
    }
}
