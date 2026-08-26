<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Retry policy exhausted: attempts.max gateway tries have been recorded.
 */
class PaymentMaxAttemptsException extends RuntimeException
{
    public static function reached(int $max): self
    {
        return new self(__('This payment has reached its maximum number of attempts. Please contact support.'));
    }
}
