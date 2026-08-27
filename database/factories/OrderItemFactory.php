<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'sku_snapshot' => fake()->bothify('SKU-#####'),
            'product_name_snapshot' => fake()->words(3, true),
            'variant_name_snapshot' => null,
            'unit_price_minor' => fake()->numberBetween(100, 10000),
            'quantity' => fake()->randomFloat(3, 1, 10),
            'discount_minor' => 0,
            'tax_minor' => 0,
            'line_total_minor' => fake()->numberBetween(100, 10000),
            'quantity_cancelled' => 0,
            'quantity_fulfilled' => 0,
            'quantity_returned' => 0,
            'quantity_refunded' => 0,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
        ]);
    }

    public function withQuantity(float $quantity): static
    {
        return $this->state(fn () => [
            'quantity' => $quantity,
        ]);
    }
}
