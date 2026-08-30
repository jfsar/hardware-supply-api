<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\OrderStateException;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\ReleaseStock;
use Illuminate\Support\Facades\DB;

/**
 * Full order cancellation (FR-ORD-003): validates the transition against
 * the OrderStatus map, releases still-active reservations, cancels
 * pending payments, records the history row with actor + reason, and
 * stamps cancelled_at. Guests cannot reach this flow (support-only).
 */
class CancelOrder
{
    public function __construct(protected ReleaseStock $releaseStock) {}

    /**
     * @throws OrderStateException when the transition is illegal
     */
    public function __invoke(Order $order, User $actor, string $reason, string $cancelledBy = 'customer'): Order
    {
        $current = $order->order_status;

        if (! $current->canTransitionTo(OrderStatus::Cancelled)) {
            throw OrderStateException::illegalTransition($current, OrderStatus::Cancelled);
        }

        return DB::transaction(function () use ($order, $actor, $reason, $cancelledBy, $current): Order {
            // Restock everything still held for this order.
            $order->loadMissing('reservations');

            foreach ($order->reservations as $reservation) {
                ($this->releaseStock)($reservation);
            }

            $order->items()->update([
                'quantity_cancelled' => DB::raw('quantity - quantity_cancelled'),
            ]);

            $order->payments()->where('status', PaymentStatus::Pending)->update([
                'status' => PaymentStatus::Cancelled->value,
            ]);

            $from = $current->value;

            $order->forceFill([
                'order_status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $order->statusHistories()->create([
                'from_status' => $from,
                'to_status' => OrderStatus::Cancelled->value,
                'changed_by_user_id' => $actor->getKey(),
                'reason' => $reason,
                'metadata' => ['cancelled_by' => $cancelledBy],
            ]);

            return $order;
        });
    }
}
