<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'cart_id' => CartFactory::new(),
            'user_id' => null,
            'status' => 'pending',
            'currency_code' => config('commerce.currency', 'PHP'),
            'subtotal_minor' => 0,
            'discount_minor' => 0,
            'shipping_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 0,
            'address_snapshot' => null,
            'expires_at' => now()->addMinutes(30),
            'completed_at' => null,
        ];
    }
}
