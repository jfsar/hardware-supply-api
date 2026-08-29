<?php

namespace Database\Factories;

use App\Models\RecentlyViewedProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecentlyViewedProduct>
 */
class RecentlyViewedProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => ProductFactory::new(),
            'viewed_at' => now(),
        ];
    }

    /**
     * A watch record for an authenticated customer.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'session_hash' => null,
        ]);
    }

    /**
     * A watch record for a guest session.
     */
    public function forSession(string $sessionHash): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'session_hash' => $sessionHash,
        ]);
    }
}
