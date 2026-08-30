<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Orders\AddOrderNote;
use App\Actions\Orders\AdminCancelOrder;
use App\Actions\Orders\AdminOrderIndex;
use App\Actions\Orders\AdminRefundOrder;
use App\Actions\Orders\ApplyOrderAdjustments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCancelOrderRequest;
use App\Http\Requests\Admin\AdminOrderIndexRequest;
use App\Http\Requests\Admin\AdminOrderNoteRequest;
use App\Http\Requests\Admin\AdminOrderRefundRequest;
use App\Http\Requests\Admin\AdminUpdateOrderRequest;
use App\Http\Resources\Admin\AdminOrderNoteResource;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Http\Resources\RefundResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Admin order administration (Phase 8 Task 2, FR-ADMIN-004…006).
 * Every mutation here appends a status-history row and an audit entry.
 */
class OrderController extends Controller
{
    /**
     * Allowlisted admin order listing (orders.view).
     */
    public function index(AdminOrderIndexRequest $request, AdminOrderIndex $indexOrders): JsonResponse
    {
        $orders = ($indexOrders)($request->validated());

        return response()->json([
            'data' => AdminOrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Full administrative order detail (orders.view).
     */
    public function show(Order $order): JsonResponse
    {
        $order->loadMissing([
            'items',
            'addresses',
            'statusHistories',
            'adjustments',
            'notes.author',
            'payments',
            'refunds',
            'shipments.method',
            'shipments.items',
            'shipments.trackingEvents',
        ]);

        return response()->json([
            'data' => new AdminOrderResource($order),
        ]);
    }

    /**
     * Append signed manual adjustments and reconcile totals (orders.update).
     */
    public function update(
        AdminUpdateOrderRequest $request,
        Order $order,
        ApplyOrderAdjustments $applyAdjustments,
    ): JsonResponse {
        $order = ($applyAdjustments)(
            $order,
            auth('sanctum')->user(),
            (array) $request->input('adjustments', []),
        );

        return response()->json([
            'data' => new AdminOrderResource($order->loadMissing([
                'statusHistories',
                'notes.author',
                'payments',
                'refunds',
            ])),
        ]);
    }

    /**
     * Admin-initiated cancellation with mandatory reason (orders.cancel).
     */
    public function cancel(
        AdminCancelOrderRequest $request,
        Order $order,
        AdminCancelOrder $cancelOrder,
    ): JsonResponse {
        $order = ($cancelOrder)($order, auth('sanctum')->user(), (string) $request->input('reason'));

        return response()->json([
            'data' => new AdminOrderResource($order->loadMissing([
                'items',
                'addresses',
                'statusHistories',
                'adjustments',
                'notes.author',
                'payments',
                'refunds',
            ])),
        ]);
    }

    /**
     * Refund the order's primary captured payment (orders.refund).
     */
    public function refund(
        AdminOrderRefundRequest $request,
        Order $order,
        AdminRefundOrder $refundOrder,
    ): JsonResponse {
        $refund = ($refundOrder)(
            $order,
            auth('sanctum')->user(),
            (int) $request->integer('amount_minor'),
            (string) $request->input('reason'),
            $request->input('remarks'),
            (array) $request->input('items', []),
        );

        return response()->json([
            'data' => new RefundResource($refund->load('payment')),
        ], 201);
    }

    /**
     * All notes for an order, internal and customer-visible (orders.view).
     */
    public function notesIndex(Order $order): JsonResponse
    {
        $order->loadMissing('notes.author');

        return response()->json([
            'data' => AdminOrderNoteResource::collection($order->notes),
        ]);
    }

    /**
     * Attach a staff note, optionally customer-visible (orders.notes).
     */
    public function notesStore(
        AdminOrderNoteRequest $request,
        Order $order,
        AddOrderNote $addNote,
    ): JsonResponse {
        $note = ($addNote)(
            $order,
            auth('sanctum')->user(),
            (array) $request->validated(),
        );

        return response()->json([
            'data' => new AdminOrderNoteResource($note),
        ], 201);
    }
}
