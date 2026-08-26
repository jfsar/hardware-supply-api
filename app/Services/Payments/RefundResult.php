<?php

namespace App\Services\Payments;

/**
 * Gateway acknowledgement of a refund submission.
 */
final class RefundResult
{
    public function __construct(
        public readonly string $providerRefundId,
        public readonly string $status,
    ) {}
}
