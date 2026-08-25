<?php

namespace App\Services\Inventory;

use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Services\Inventory\Concerns\RecordsMovements;
use Illuminate\Support\Facades\DB;

class ConsumeStock
{
    use RecordsMovements;

    /**
     * Fulfil a reservation on payment success (FR-INV-006): the reserved
     * units leave the building, so both on-hand and reserved quantities
     * drop and a Sale ledger row records the outflow.
     *
     * Consuming a terminal reservation is a no-op returning current state.
     */
    public function __invoke(int|InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation): InventoryReservation {
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

            $before = (float) $inventory->quantity_on_hand;
            $inventory->quantity_on_hand -= $quantity;
            $inventory->quantity_reserved -= $quantity;
            $inventory->save();

            $reservation->status = ReservationStatus::Consumed;
            $reservation->consumed_at = now();
            $reservation->save();

            $this->recordMovement(
                $inventory,
                MovementType::Sale,
                -$quantity,
                $before,
                $inventory->quantity_on_hand,
                null,
                $reservation,
            );

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
