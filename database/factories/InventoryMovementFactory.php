<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => LocationFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'movement_type' => 'purchase',
            'quantity_delta' => 10.0,
            'quantity_before' => 0.0,
            'quantity_after' => 10.0,
            'reference_type' => null,
            'reference_id' => null,
            'reason' => null,
            'performed_by_user_id' => null,
        ];
    }
}
