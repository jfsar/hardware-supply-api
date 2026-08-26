<?php

namespace App\Exceptions\Checkout;

use App\Enums\PaymentMethod;
use RuntimeException;

/**
 * Gateway-driven payment methods initialize their flows in Phase 5.
 */
class PaymentMethodUnavailableException extends RuntimeException
{
    public readonly string $method;

    public static function forMethod(PaymentMethod $method): self
    {
        $exception = new self(__('This payment method is not accepted yet. Please use cash on delivery.'));
        $exception->method = $method->value;

        return $exception;
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return ['method' => $this->method];
    }
}
