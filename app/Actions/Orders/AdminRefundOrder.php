<?php

namespace App\Actions\Orders;

use App\Actions\Payments\CreateRefund;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\RefundExceedsBalanceException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Validation\ValidationException;

/**
 * Admin whole-order refund (Phase 8, FR-ADMIN-005): delegates to the
 * Phase 5 CreateRefund action bound to the order's primary captured
 * payment. The action only stages the refund outbox row — the gateway
 * call stays queued as always.
 */
class AdminRefundOrder
{
    public function __construct(
        protected CreateRefund $createRefund,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  list<array{item: int, quantity: float|int}>  $items
     *
     * @throws ValidationException when the order has no captured funds
     * @throws RefundExceedsBalanceException when the amount exceeds the remaining balance
     */
    public function __invoke(
        Order $order,
        User $actor,
        int $amountMinor,
        string $reason,
        ?string $remarks,
        array $items = [],
    ): Refund {
        $payment = $order->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'amount_minor' => __('This order has no captured payment to refund.'),
            ]);
        }

        /** @var Payment $payment */
        $refund = ($this->createRefund)(
            $payment,
            $amountMinor,
            $reason,
            $remarks,
            $items,
            $actor,
        );

        ($this->recordAuditLog)($actor, 'refund.requested_from_order', 'Refund', (int) $refund->getKey(), null, [
            'order_number' => $order->order_number,
            'amount_minor' => $amountMinor,
            'currency_code' => $refund->currency_code,
        ]);

        return $refund;
    }
}
