<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fulfillment\FulfillOrder;
use App\Actions\Fulfillment\RecordTrackingEvent;
use App\Enums\ShipmentStatus;
use App\Exceptions\Orders\OrderStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FulfillOrderRequest;
use App\Http\Requests\Admin\RecordTrackingEventRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Admin fulfillment endpoints (Phase 6 Task 4): order detail with
 * shipments, shipment creation from line allocations, and append-only
 * tracking events.
 */
class FulfillmentController extends Controller
{
    /**
     * Order detail including its shipments (orders.view).
     */
    public function show(Order $order): JsonResponse
    {
        $order->loadMissing([
            'items',
            'addresses',
            'statusHistories',
            'shipments.method',
            'shipments.items',
            'shipments.trackingEvents',
        ]);

        return response()->json(['data' => new OrderResource($order)]);
    }

    /**
     * Create one shipment from an allocation map (orders.fulfill).
     *
     * @throws OrderStateException
     * @throws ValidationException
     */
    public function fulfill(
        FulfillOrderRequest $request,
        Order $order,
        FulfillOrder $fulfillOrder,
    ): JsonResponse {
        $allocations = array_map(
            fn (mixed $quantity): float => (float) $quantity,
            (array) $request->input('items'),
        );

        $shipment = ($fulfillOrder)(
            $order,
            auth('sanctum')->user(),
            $allocations,
            $request->integer('delivery_driver_id') ?: null,
            $request->input('tracking_number'),
            $request->input('carrier_name'),
        );

        return response()->json(['data' => new ShipmentResource(
            $shipment->load('items'),
        )], 201);
    }

    /**
     * Append a tracking event and move the shipment (orders.fulfill).
     */
    public function tracking(
        RecordTrackingEventRequest $request,
        Shipment $shipment,
        RecordTrackingEvent $recordTrackingEvent,
    ): JsonResponse {
        $event = ($recordTrackingEvent)(
            $shipment,
            auth('sanctum')->user(),
            ShipmentStatus::from((string) $request->input('status')),
            $request->input('location_text'),
            $request->date('event_at'),
            $request->input('description'),
            $request->input('raw_payload'),
        );

        $shipment->refresh()->loadMissing([
            'items',
            'trackingEvents',
            'method',
        ]);

        return response()->json([
            'data' => new ShipmentResource($shipment),
            'event' => [
                'id' => $event->getKey(),
                'status' => $event->status,
                'event_at' => optional($event->event_at)->toISOString(),
            ],
        ]);
    }
}
