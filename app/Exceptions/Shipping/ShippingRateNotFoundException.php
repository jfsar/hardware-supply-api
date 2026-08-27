<?php

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * No active zone or rate matched the destination and method for a
 * shipping quote (SRS §22, Phase 6 Task 2). Thrown by PhpRateCalculator
 * to halt checkout when shipping is required but unresolvable.
 */
class ShippingRateNotFoundException extends RuntimeException
{
    public readonly ?string $methodCode;

    public readonly ?string $reason;

    public static function forDestination(string $methodCode): self
    {
        $exception = new self(__('No active shipping zone matches this destination.'));
        $exception->methodCode = $methodCode;
        $exception->reason = 'zone_not_found';

        return $exception;
    }

    public static function forMethod(string $methodCode): self
    {
        $exception = new self(__('No active shipping rate is available for the selected method.'));
        $exception->methodCode = $methodCode;
        $exception->reason = 'rate_not_found';

        return $exception;
    }

    /**
     * @return array<string, string|null>
     */
    public function details(): array
    {
        return ['method' => $this->methodCode, 'reason' => $this->reason];
    }
}
