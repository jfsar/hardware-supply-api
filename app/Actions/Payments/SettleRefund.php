<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TransactionType;
use App\Exceptions\Payments\PaymentStateException;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes a refund outcome exactly once (SRS §55): flips the row to
 * Succeeded, advances order_items.quantity_refunded, and recomputes the
 * payment's refunded aggregate. Both ProcessRefund and the webhook
 * consumer funnel through here, so concurrent confirmations cannot
 * double-apply quantities or aggregate state.
 */
class SettleRefund
{
    /**
     * @throws PaymentStateException
     */
    public function __invoke(Refund $refund): Refund
    {
        return DB::transaction(function () use ($refund): Refund {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RefundStatus::Succeeded) {
                return $locked; // Idempotent replay.
            }

            if ($locked->status !== RefundStatus::Pending) {
                throw new PaymentStateException(__('This refund has already been finalized.'));
            }

            $locked->forceFill([
                'status' => RefundStatus::Succeeded,
                'processed_at' => now(),
            ])->save();

            foreach ($locked->items as $item) {
                OrderItem::query()
                    ->whereKey($item->order_item_id)
                    ->increment('quantity_refunded', $item->quantity);
            }

            /** @var Payment $payment */
            $payment = $locked->payment()->lockForUpdate()->firstOrFail();
            $this->refreshAggregate($payment);

            return $locked;
        });
    }

    /**
     * Recompute the payment's refunded aggregate from settled facts only.
     */
    public function refreshAggregate(Payment $payment): void
    {
        $payment->forceFill(['status' => $this->aggregateFor($payment)])->save();
    }

    /**
     * Aggregate payment status derived from settled facts only.
     */
    protected function aggregateFor(Payment $payment): PaymentStatus
    {
        $captured = (int) $payment->transactions()
            ->where('transaction_type', TransactionType::Charge->value)
            ->where('status', 'succeeded')
            ->sum('amount_minor');
        $refunded = (int) $payment->refunds()
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount_minor');

        if ($refunded <= 0) {
            return PaymentStatus::Paid;
        }

        if ($captured > 0 && $refunded >= $captured) {
            return PaymentStatus::Refunded;
        }

        return PaymentStatus::PartiallyRefunded;
    }
}
