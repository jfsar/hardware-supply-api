<?php

namespace Database\Factories;

use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Pricing',
            'code' => strtoupper($this->faker->unique()->bothify('PL-####')),
            'currency_code' => config('commerce.currency', 'PHP'),
            'customer_scope' => 'all',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * The fallback list every shopper resolves against.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'DEFAULT',
            'is_default' => true,
        ]);
    }

    /**
     * A list visible only to entitled customers.
     */
    public function customerScoped(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_scope' => 'customer',
            'is_default' => false,
        ]);
    }
}
