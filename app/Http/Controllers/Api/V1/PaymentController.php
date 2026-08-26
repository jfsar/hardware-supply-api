<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\CancelGatewayPayment;
use App\Actions\Payments\CreateGatewayPayment;
use App\Exceptions\Payments\PaymentStateException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway payment session endpoints (Phase 5 Task 3): open a hosted
 * checkout for an order's pending gateway payment, retry it, or abandon
 * it. All owner-scoped and wrapped by idempotency middleware.
 */
class PaymentController extends Controller
{
    /**
     * Open (or re-open) a checkout session for the order's gateway payment.
     *
     * @throws PaymentStateException
     */
    public function store(Request $request, Order $order, CreateGatewayPayment $createGatewayPayment): JsonResponse
    {
        $this->authorizeOwner($request, $order);

        /** @var Payment|null $payment */
        $payment = $order->payments()
            ->where('provider', 'payrex')
            ->orderByDesc('id')
            ->first()
            // No gateway row? Hand the newest row to the action so COD and
            // other non-gateway methods surface as proper state errors.
            ?? $order->payments()->orderByDesc('id')->first();

        abort_unless($payment !== null, 404);

        $result = ($createGatewayPayment)($payment);

        return response()->json(['data' => [
            'payment' => new PaymentResource($result['payment']),
            'redirect_url' => $result['redirect_url'],
        ]]);
    }

    /**
     * Start another attempt on a specific gateway payment.
     *
     * @throws PaymentStateException
     */
    public function retry(Request $request, Payment $payment, CreateGatewayPayment $createGatewayPayment): JsonResponse
    {
        $this->authorizeOwner($request, $payment->order()->firstOrFail());

        $result = ($createGatewayPayment)($payment);

        return response()->json(['data' => [
            'payment' => new PaymentResource($result['payment']),
            'redirect_url' => $result['redirect_url'],
        ]]);
    }

    /**
     * Abandon an open checkout session.
     *
     * @throws PaymentStateException
     */
    public function cancel(Request $request, Payment $payment, CancelGatewayPayment $cancelGatewayPayment): JsonResponse
    {
        $this->authorizeOwner($request, $payment->order()->firstOrFail());

        $cancelled = ($cancelGatewayPayment)($payment);

        return response()->json(['data' => [
            'payment' => new PaymentResource($cancelled),
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
