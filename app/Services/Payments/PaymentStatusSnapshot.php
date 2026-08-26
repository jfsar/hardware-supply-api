<?php

namespace App\Services\Payments;

/**
 * Normalized provider-side state used by reconciliation to align local
 * rows without trusting redirects or stale webhooks (FR-PAY-004).
 */
final class PaymentStatusSnapshot
{
    public function __construct(
        public readonly string $intentStatus,
        public readonly ?string $latestPaymentId = null,
        public readonly ?string $latestPaymentStatus = null,
    ) {}
}
