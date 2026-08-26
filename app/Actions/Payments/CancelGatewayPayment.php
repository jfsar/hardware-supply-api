<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\PaymentStateException;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Customer-initiated abandonment: expire the provider checkout session and
 * mark the payment Cancelled (Phase 5 Task 3). Order-level cancellation —
 * including reservation release — remains owned by CancelOrder so stock
 * policy stays in exactly one place.
 */
class CancelGatewayPayment
{
    public function __construct(
        protected PaymentGateway $gateway,
    ) {}

    /**
     * @throws PaymentStateException
     */
    public function __invoke(Payment $payment): Payment
    {
        $this->assertCancellable($payment);

        $sessionId = $payment->metadata['provider_session_id'] ?? null;

        if (is_string($sessionId) && $sessionId !== '') {
            ($this->gateway)->expireSession($sessionId);
        }

        return DB::transaction(function () use ($payment): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                throw PaymentStateException::forStatus($locked, 'cancelled');
            }

            $locked->forceFill(['status' => PaymentStatus::Cancelled])->save();

            return $locked;
        });
    }

    /**
     * @throws PaymentStateException
     */
    protected function assertCancellable(Payment $payment): void
    {
        if ($payment->method() === PaymentMethod::Cod) {
            throw PaymentStateException::forStatus($payment, 'cancelled through the gateway');
        }

        /** @var PaymentStatus $status */
        $status = $payment->status;

        if (! in_array($status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
            throw PaymentStateException::forStatus($payment, 'cancelled');
        }
    }
}
