<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CancelOrderItems;
use App\Exceptions\Orders\OrderStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderItemsRequest;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer order endpoints (FR-ORD-010). Every lookup is owner-scoped;
 * guests cannot access or cancel orders.
 */
class OrderController extends Controller
{
    /**
     * The customer's orders, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->with('items')
            ->latest('created_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return OrderResource::collection($orders);
    }

    /**
     * One order with line snapshots and status history.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $order->loadMissing(['items', 'addresses', 'statusHistories']);

        return response()->json(['data' => new OrderResource($order)]);
    }

    /**
     * Cancel the whole order (legal transitions only).
     *
     * @throws OrderStateException
     */
    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancelOrder): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        $cancelled = ($cancelOrder)($order, $request->user(), (string) $request->input('reason'));

        return response()->json(['data' => [
            'message' => __('Order cancelled.'),
            'order' => new OrderResource($cancelled->loadMissing('items')),
        ]]);
    }

    /**
     * Cancel specific quantities on specific lines (FR-ORD-004).
     *
     * @throws OrderStateException
     */
    public function cancelItems(
        CancelOrderItemsRequest $request,
        Order $order,
        CancelOrderItems $cancelOrderItems,
    ): JsonResponse {
        $this->authorizeOwner($request, $order);

        $cancelled = ($cancelOrderItems)(
            $order,
            $request->user(),
            (array) $request->input('items'),
            (string) $request->input('reason'),
        );

        return response()->json(['data' => [
            'message' => __('Order lines cancelled.'),
            'order' => new OrderResource($cancelled->loadMissing('items')),
        ]]);
    }

    /**
     * Ownership enforced inline per house convention; anything else 404s.
     */
    protected function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless((int) $order->user_id === (int) $request->user()?->getAuthIdentifier(), 404);
    }
}
