<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * A retry arrived inside the exponential backoff window (FR-PAY-007).
 */
class PaymentBackoffException extends RuntimeException
{
    public readonly int $retry_after_seconds;

    public static function active(int $seconds): self
    {
        $exception = new self(__('Please wait before retrying this payment.'));
        $exception->retry_after_seconds = $seconds;

        return $exception;
    }

    /**
     * @return array<string, int>
     */
    public function details(): array
    {
        return ['retry_after_seconds' => $this->retry_after_seconds];
    }
}
