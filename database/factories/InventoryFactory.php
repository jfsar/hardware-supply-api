<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state: no stock, nothing reserved.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => LocationFactory::new(),
            'product_variant_id' => ProductVariantFactory::new(),
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'reorder_level' => 0,
        ];
    }

    /**
     * Attach the stock row to a specific variant and location.
     */
    public function forVariant(ProductVariant $variant, ?Location $location = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant_id' => $variant->id,
            'location_id' => $location?->id ?? Location::primaryWarehouse()?->id ?? LocationFactory::new()->primary()->create()->id,
        ]);
    }

    /**
     * Give the row on-hand stock.
     */
    public function withOnHand(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => $quantity,
        ]);
    }

    /**
     * Hold part of the on-hand stock as reserved.
     */
    public function withReserved(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => max($attributes['quantity_on_hand'], $quantity),
            'quantity_reserved' => $quantity,
        ]);
    }

    /**
     * Set a reorder level for low-stock assertions.
     */
    public function withReorderLevel(float $level): static
    {
        return $this->state(fn (array $attributes) => [
            'reorder_level' => $level,
        ]);
    }

    /**
     * Introduce on-hand stock through a purchase movement so the ledger
     * stays truthful (mirrors AdjustInventory semantics).
     */
    public function stocked(float $quantity = 10.0): static
    {
        return $this->afterCreating(function (Inventory $inventory) use ($quantity): void {
            $before = (float) $inventory->quantity_on_hand;
            $inventory->quantity_on_hand = $before + $quantity;
            $inventory->save();

            InventoryMovement::query()->create([
                'location_id' => $inventory->location_id,
                'product_variant_id' => $inventory->product_variant_id,
                'movement_type' => MovementType::Purchase,
                'quantity_delta' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $inventory->quantity_on_hand,
                'reason' => 'factory stock seed',
            ]);
        });
    }
}
