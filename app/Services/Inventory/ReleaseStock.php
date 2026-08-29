<?php

namespace App\Services\Inventory;

use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Events\InventoryBecameAvailable;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Services\Inventory\Concerns\RecordsMovements;
use Illuminate\Support\Facades\DB;

class ReleaseStock
{
    use RecordsMovements;

    /**
     * Give reserved stock back on cancel/failure/expiry (FR-INV-008): only
     * the reservation is lifted — on-hand stock never left for a release.
     *
     * Releasing a terminal reservation is a no-op returning current state.
     */
    public function __invoke(int|InventoryReservation $reservation, bool $expired = false): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $expired): InventoryReservation {
            /** @var InventoryReservation $reservation */
            $reservation = InventoryReservation::query()
                ->whereKey($reservation instanceof InventoryReservation ? $reservation->getKey() : $reservation)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status->isTerminal()) {
                return $reservation;
            }

            $inventory = $this->lockInventory($reservation);
            $quantity = (float) $reservation->quantity;

            $before = $inventory->availableQuantity();
            $inventory->quantity_reserved -= $quantity;
            $inventory->save();

            $reservation->status = $expired ? ReservationStatus::Expired : ReservationStatus::Released;
            $reservation->released_at = now();
            $reservation->save();

            $this->recordMovement(
                $inventory,
                MovementType::ReservationRelease,
                $quantity,
                $before,
                $inventory->availableQuantity(),
                null,
                $reservation,
            );

            if ($before <= 0.0 && $inventory->availableQuantity() > 0.0) {
                $variant = ProductVariant::withTrashed()->find($reservation->product_variant_id);

                if ($variant !== null) {
                    event(new InventoryBecameAvailable($variant));
                }
            }

            return $reservation;
        });
    }

    /**
     * Lock the stock row backing a reservation.
     */
    private function lockInventory(InventoryReservation $reservation): Inventory
    {
        return Inventory::query()
            ->where('location_id', $reservation->location_id)
            ->where('product_variant_id', $reservation->product_variant_id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
