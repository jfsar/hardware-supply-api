<?php

namespace App\Services\Payments;

/**
 * Gateway response for a newly opened hosted checkout session.
 */
final class PaymentResult
{
    public function __construct(
        public readonly string $providerSessionId,
        public readonly string $redirectUrl,
        public readonly ?string $providerPaymentId = null,
    ) {}
}
