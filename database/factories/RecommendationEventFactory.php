<?php

namespace Database\Factories;

use App\Models\RecommendationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationEvent>
 */
class RecommendationEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => ProductFactory::new(),
            'event_type' => 'impression',
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
