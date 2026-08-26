<?php

namespace App\Exceptions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use RuntimeException;

/**
 * The payment row is not in a state that permits the requested operation.
 */
class PaymentStateException extends RuntimeException
{
    public readonly ?string $payment_ulid;

    public readonly ?string $status;

    public static function forStatus(Payment $payment, string $operation): self
    {
        $exception = new self(__('This payment cannot be :operation in its current state.', [
            'operation' => $operation,
        ]));
        $exception->payment_ulid = $payment->ulid;
        $exception->status = $payment->status instanceof PaymentStatus
            ? $payment->status->value
            : (string) $payment->status;

        return $exception;
    }

    /**
     * @return array<string, string|null>
     */
    public function details(): array
    {
        return ['payment' => $this->payment_ulid, 'status' => $this->status];
    }
}
