<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => CartFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'quantity' => 1.0,
        ];
    }

    /**
     * Attach the line to a specific cart.
     */
    public function forCart(Cart $cart): static
    {
        return $this->state(fn (array $attributes) => ['cart_id' => $cart->id]);
    }

    /**
     * Point the line at a specific variant with a quantity.
     */
    public function forVariant(ProductVariant $variant, float $quantity = 1.0): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }
}
