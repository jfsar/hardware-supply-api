<?php

namespace App\Actions\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\Orders\OrderStateException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RecordAuditLog;
use App\Support\ShipmentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create one shipment from a line-allocation map (Phase 6 Task 4,
 * FR-SHIP-004/005, SRS §32). Repeating the action splits an order across
 * multiple shipments; item quantity conservation is enforced inside the
 * transaction under row locks so concurrent fulfills can never exceed
 * ordered minus cancelled minus already-fulfilled.
 */
class FulfillOrder
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<int, float>  $allocations  map of order_item_id => quantity
     *
     * @throws OrderStateException when the order state forbids fulfillment
     * @throws ValidationException when an allocation is invalid or over-allocates
     */
    public function __invoke(
        Order $order,
        ?User $actor,
        array $allocations,
        ?int $deliveryDriverId = null,
        ?string $trackingNumber = null,
        ?string $carrierName = null,
    ): Shipment {
        return DB::transaction(function () use ($order, $actor, $allocations, $deliveryDriverId, $trackingNumber, $carrierName): Shipment {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->loadMissing('checkoutSession.shippingMethod');

            $this->assertFulfillable($locked);

            /** @var list<OrderItem> $items */
            $items = $locked->items()->lockForUpdate()->get()->all();
            $fulfillMap = $this->buildAllocationMap($items, $allocations);

            /** @var Shipment $shipment */
            $shipment = $this->createShipment($locked, $fulfillMap, $deliveryDriverId, $trackingNumber, $carrierName);

            $this->incrementFulfilled($fulfillMap);

            $previous = $locked->fulfillment_status;
            $target = $this->fulfillmentTarget($locked);

            $locked->forceFill(['fulfillment_status' => $target])->save();

            $locked->statusHistories()->create([
                'from_status' => $previous->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor?->getKey(),
                'reason' => 'fulfillment',
                'metadata' => [
                    'shipment_number' => $shipment->shipment_number,
                    'shipment_ulid' => $shipment->ulid,
                    'partial' => $target === FulfillmentStatus::PartiallyFulfilled,
                    'allocated' => array_map(fn (float $qty): float => $qty, $fulfillMap),
                ],
            ]);

            ($this->recordAuditLog)($actor, 'order.fulfilled', 'Shipment', (int) $shipment->getKey(), null, [
                'order_number' => $locked->order_number,
                'shipment_number' => $shipment->shipment_number,
                'fulfillment_status' => $target->value,
            ]);

            return $shipment->load('items');
        });
    }

    /**
     * Cancelled/expired orders cannot ship; already-fulfilled orders have
     * nothing left to allocate.
     *
     * @throws OrderStateException
     */
    protected function assertFulfillable(Order $order): void
    {
        if (in_array($order->order_status, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
            throw OrderStateException::illegalTransition($order->order_status, OrderStatus::Fulfilled);
        }

        if ($order->fulfillment_status === FulfillmentStatus::Fulfilled) {
            throw OrderStateException::illegalTransition($order->order_status, OrderStatus::Fulfilled);
        }
    }

    /**
     * Validate and normalize the allocation map against the order's
     * still-available quantities.
     *
     * @param  list<OrderItem>  $items
     * @param  array<int, float>  $allocations
     * @return array<int, float>
     *
     * @throws ValidationException
     */
    protected function buildAllocationMap(array $items, array $allocations): array
    {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'items' => __('An allocation is required to create a shipment.'),
            ]);
        }

        /** @var array<int, OrderItem> $byId */
        $byId = [];

        foreach ($items as $item) {
            $byId[(int) $item->getKey()] = $item;
        }

        $map = [];

        foreach ($allocations as $itemId => $quantity) {
            $line = $byId[(int) $itemId] ?? null;

            if ($line === null) {
                throw ValidationException::withMessages([
                    'items' => __('One of the selected order lines does not belong to this order.'),
                ]);
            }

            $requested = (float) $quantity;

            if ($requested <= 0) {
                throw ValidationException::withMessages([
                    'items' => __('Allocated quantities must be greater than zero.'),
                ]);
            }

            $available = $line->remainingQuantity() - (float) $line->quantity_fulfilled;

            if ($requested > $available + 0.0001) {
                throw ValidationException::withMessages([
                    'items' => __('The requested quantity exceeds what remains unfulfilled on this order line.'),
                ]);
            }

            $map[(int) $itemId] = ($map[(int) $itemId] ?? 0.0) + $requested;
        }

        return $map;
    }

    /**
     * Create the shipment header, its item rows, and the delivery/timing
     * snapshots carried forward from checkout.
     *
     * @param  array<int, float>  $fulfillMap
     */
    protected function createShipment(
        Order $order,
        array $fulfillMap,
        ?int $deliveryDriverId,
        ?string $trackingNumber,
        ?string $carrierName,
    ): Shipment {
        $method = $order->checkoutSession?->shippingMethod;

        if ($method === null) {
            throw OrderStateException::illegalTransition($order->order_status, OrderStatus::Fulfilled);
        }

        $session = $order->checkoutSession;
        $estimateDays = $session?->shipping_estimated_max_days ?? $session?->shipping_estimated_min_days;

        $shippingAddress = $order->addresses()
            ->where('address_type', 'shipping')
            ->first();

        /** @var array<string, mixed>|null $addressSnapshot */
        $addressSnapshot = $shippingAddress?->only([
            'country_id', 'region_id', 'province_id', 'city_id', 'barangay_id',
            'postal_code_id', 'address_line1', 'address_line2', 'recipient_name',
            'recipient_phone', 'notes',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->getKey(),
            'shipping_method_id' => $method->getKey(),
            'pickup_location_id' => $session?->pickup_location_id,
            'delivery_driver_id' => $deliveryDriverId,
            'shipment_number' => ShipmentNumber::generateUnique(
                fn (string $candidate): bool => Shipment::query()->where('shipment_number', $candidate)->exists()
            ),
            'status' => ShipmentStatus::Pending,
            'tracking_number' => $trackingNumber,
            'carrier_name' => $carrierName,
            'estimated_delivery_at' => $estimateDays !== null ? now()->addDays((int) $estimateDays) : null,
            'delivery_address_snapshot' => $addressSnapshot,
        ]);

        foreach ($fulfillMap as $itemId => $quantity) {
            $shipment->items()->create([
                'order_item_id' => (int) $itemId,
                'quantity' => (float) $quantity,
            ]);
        }

        return $shipment;
    }

    /**
     * @param  array<int, float>  $fulfillMap
     */
    protected function incrementFulfilled(array $fulfillMap): void
    {
        foreach ($fulfillMap as $itemId => $quantity) {
            OrderItem::query()->whereKey((int) $itemId)->increment('quantity_fulfilled', (float) $quantity);
        }
    }

    /**
     * Fulfilled when every non-cancelled quantity is covered; otherwise
     * partially fulfilled (FR-SHIP-004).
     */
    protected function fulfillmentTarget(Order $order): FulfillmentStatus
    {
        $complete = $order->items()->get()->every(
            fn (OrderItem $item): bool => (float) $item->quantity_fulfilled >= $item->remainingQuantity() - 0.0001,
        );

        return $complete ? FulfillmentStatus::Fulfilled : FulfillmentStatus::PartiallyFulfilled;
    }
}
