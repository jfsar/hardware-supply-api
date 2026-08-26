<?php

namespace App\Services\Payments;

/**
 * A refund instruction for an already-captured provider payment.
 */
final class RefundRequest
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $reason,
        public readonly ?string $remarks = null,
        public readonly array $metadata = [],
    ) {}
}
