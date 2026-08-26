<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payments\CreateRefund;
use App\Exceptions\Payments\PaymentStateException;
use App\Exceptions\Payments\RefundExceedsBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreateRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * Admin refund endpoint (Phase 5 Task 5): bounded by captured funds and
 * allocated across order lines. The provider call is queued via the
 * outbox row this action creates.
 */
class RefundController extends Controller
{
    /**
     * @throws PaymentStateException
     * @throws RefundExceedsBalanceException
     */
    public function store(CreateRefundRequest $request, Payment $payment, CreateRefund $createRefund): JsonResponse
    {
        $refund = ($createRefund)(
            $payment,
            (int) $request->integer('amount_minor'),
            (string) $request->input('reason'),
            $request->input('remarks'),
            (array) $request->input('items', []),
        );

        return response()->json(['data' => new RefundResource($refund)], 201);
    }
}
