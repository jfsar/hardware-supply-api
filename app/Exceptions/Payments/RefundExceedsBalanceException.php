<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * A refund would exceed captured-minus-refunded funds (FR-PAY-008).
 */
class RefundExceedsBalanceException extends RuntimeException
{
    public readonly int $remaining_minor;

    public static function forAmount(int $remainingMinor): self
    {
        $exception = new self(__('The refund amount exceeds the remaining refundable balance.'));
        $exception->remaining_minor = $remainingMinor;

        return $exception;
    }

    /**
     * @return array<string, int>
     */
    public function details(): array
    {
        return ['remaining_minor' => $this->remaining_minor];
    }
}
