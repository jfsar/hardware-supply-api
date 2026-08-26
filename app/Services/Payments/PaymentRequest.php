<?php

namespace App\Services\Payments;

/**
 * Everything the gateway needs to open a hosted checkout session for one
 * payment attempt. Amounts are integer minor units; provider method names
 * are resolved by the caller from config('payments.methods').
 */
final class PaymentRequest
{
    /**
     * @param  string  $reference  Internal correlation id (payment ULID).
     * @param  list<string>  $paymentMethods  Provider payment-method identifiers.
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $reference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $description,
        public readonly array $paymentMethods,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly array $metadata = [],
    ) {}
}
