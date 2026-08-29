<?php

namespace Database\Factories;

use App\Models\ProductComparisonItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductComparisonItem>
 */
class ProductComparisonItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comparison_id' => ProductComparisonFactory::new(),
            'product_id' => ProductFactory::new(),
            'sort_order' => 0,
        ];
    }
}
