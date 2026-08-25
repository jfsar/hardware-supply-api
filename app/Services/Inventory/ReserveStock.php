<?php

namespace App\Services\Inventory;

use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Services\Inventory\Concerns\RecordsMovements;

class ReserveStock
{
    use RecordsMovements;

    /**
     * Hold stock for a set of items at one location. Must be called inside
     * the checkout transaction (FR-INV-006…009); nothing is written unless
     * every item fits, and rows are locked deterministically to avoid
     * deadlocks between concurrent checkouts.
     *
     * @param  array<int, array{variant_id: int, quantity: float}>  $items
     * @param  int|null  $orderId  owning order once known (Phase 4)
     * @param  int|null  $cartId  originating cart when applicable
     * @return array<int, int> created reservation ids
     *
     * @throws InsufficientStockException listing offending SKUs
     */
    public function __invoke(?int $orderId, ?int $cartId, array $items, int $locationId): array
    {
        $requested = [];

        foreach ($items as $item) {
            $variantId = (int) $item['variant_id'];
            $requested[$variantId] = ($requested[$variantId] ?? 0.0) + (float) $item['quantity'];
        }

        /** @var array<int, Inventory> $inventories */
        $inventories = Inventory::query()
            ->where('location_id', $locationId)
            ->whereIn('product_variant_id', array_keys($requested))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        $skus = ProductVariant::query()
            ->whereIn('id', array_keys($requested))
            ->pluck('sku', 'id');

        $shortfalls = [];

        foreach ($requested as $variantId => $quantity) {
            $available = isset($inventories[$variantId])
                ? $inventories[$variantId]->availableQuantity()
                : 0.0;

            if ($available < $quantity) {
                $shortfalls[(string) $skus[$variantId]] = $quantity;
            }
        }

        if ($shortfalls !== []) {
            throw InsufficientStockException::forSkus($shortfalls);
        }

        $reservationIds = [];

        foreach ($requested as $variantId => $quantity) {
            $inventory = $inventories[$variantId];

            $before = $inventory->availableQuantity();
            $inventory->quantity_reserved += $quantity;
            $inventory->save();

            $reservation = InventoryReservation::query()->create([
                'location_id' => $locationId,
                'product_variant_id' => $variantId,
                'cart_id' => $cartId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => ReservationStatus::Active,
                'expires_at' => now()->addMinutes((int) config('checkout.reservation_ttl', 15)),
            ]);

            $this->recordMovement(
                $inventory,
                MovementType::Reservation,
                -$quantity,
                $before,
                $inventory->availableQuantity(),
                null,
                $reservation,
            );

            $reservationIds[] = (int) $reservation->getKey();
        }

        return $reservationIds;
    }
}
