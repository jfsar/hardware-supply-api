<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'user_id' => null,
            'session_token_hash' => hash('sha256', Str::random(40)),
            'status' => 'active',
            'currency_code' => config('commerce.currency', 'PHP'),
            'expires_at' => now()->addDays((int) config('commerce.cart.ttl_days', 30)),
        ];
    }

    /**
     * A cart owned by an authenticated customer.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'session_token_hash' => null,
        ]);
    }

    /**
     * A guest cart bound to a known token hash.
     */
    public function withTokenHash(string $tokenHash): static
    {
        return $this->state(fn (array $attributes) => [
            'session_token_hash' => $tokenHash,
        ]);
    }
}
