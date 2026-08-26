<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Orders\OrderStateException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Inventory\ReleaseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partial line cancellation (FR-ORD-004): cancels quantities on specific
 * order items, releases that stock back through the release path, and
 * moves the order to PartiallyCancelled — or Cancelled when nothing
 * remains active. History rows record every transition.
 */
class CancelOrderItems
{
    public function __construct(protected ReleaseStock $releaseStock) {}

    /**
     * @param  list<array{item: int, quantity: float}>  $items  item id + quantity to cancel
     *
     * @throws OrderStateException when the order state forbids cancellation
     * @throws ValidationException when a requested quantity exceeds what remains
     */
    public function __invoke(Order $order, User $actor, array $items, string $reason): Order
    {
        $current = $order->order_status;

        if (! $current->isCancellable()) {
            throw OrderStateException::illegalTransition($current, OrderStatus::PartiallyCancelled);
        }

        return DB::transaction(function () use ($order, $actor, $items, $reason, $current): Order {
            /** @var array<int, float> $cancelByItemId */
            $cancelByItemId = [];

            foreach ($items as $entry) {
                /** @var OrderItem|null $item */
                $item = $order->items()->whereKey((int) $entry['item'])->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'items' => __('One of the selected order lines does not belong to this order.'),
                    ]);
                }

                $remaining = $item->remainingQuantity();
                $requested = min((float) $entry['quantity'], $remaining);

                if ($requested <= 0) {
                    throw ValidationException::withMessages([
                        'items' => __('Nothing remains cancellable on one of the selected lines.'),
                    ]);
                }

                $cancelByItemId[$item->getKey()] = ($cancelByItemId[$item->getKey()] ?? 0.0) + $requested;
            }

            // Release active reservations covering the affected variants so
            // cancelled quantity returns to availability via the release path.
            $affectedVariantIds = OrderItem::query()
                ->whereIn('id', array_keys($cancelByItemId))
                ->pluck('product_variant_id')
                ->filter()
                ->unique();

            $order->loadMissing('reservations');

            foreach ($order->reservations as $reservation) {
                if (! $reservation->status->isActive()) {
                    continue;
                }

                if ($affectedVariantIds->contains((int) $reservation->product_variant_id)) {
                    ($this->releaseStock)($reservation);
                }
            }

            foreach ($cancelByItemId as $itemId => $quantity) {
                OrderItem::query()
                    ->whereKey($itemId)
                    ->increment('quantity_cancelled', $quantity);
            }

            $allCancelled = $order->items()
                ->get()
                ->every(fn (OrderItem $item): bool => $item->remainingQuantity() <= 0.0);

            $target = $allCancelled ? OrderStatus::Cancelled : OrderStatus::PartiallyCancelled;

            if ($target !== $current) {
                $order->forceFill([
                    'order_status' => $target,
                    'cancelled_at' => $target === OrderStatus::Cancelled ? now() : $order->cancelled_at,
                ])->save();

                $order->statusHistories()->create([
                    'from_status' => $current->value,
                    'to_status' => $target->value,
                    'changed_by_user_id' => $actor->getKey(),
                    'reason' => $reason,
                    'metadata' => ['cancelled_by' => 'customer', 'partial' => ! $allCancelled],
                ]);
            }

            return $order->refresh();
        });
    }
}
