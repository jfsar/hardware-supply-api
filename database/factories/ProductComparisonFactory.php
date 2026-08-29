<?php

namespace Database\Factories;

use App\Models\ProductComparison;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductComparison>
 */
class ProductComparisonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_hash' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'session_hash' => null,
        ]);
    }

    public function forSession(string $sessionHash): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'session_hash' => $sessionHash,
        ]);
    }
}
