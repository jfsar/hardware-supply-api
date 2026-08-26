<?php

namespace App\Contracts;

use App\Exceptions\Payments\WebhookSignatureException;
use App\Services\Payments\PaymentRequest;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStatusSnapshot;
use App\Services\Payments\RefundRequest;
use App\Services\Payments\RefundResult;
use App\Services\Payments\WebhookEvent;

/**
 * The only payment boundary the application may depend on (SRS §19,
 * FR-PAY-001). Implementations must never leak provider types past this
 * interface; transport failures throw ProviderException and untrusted
 * inbound events throw WebhookSignatureException.
 */
interface PaymentGateway
{
    /**
     * The internal provider identifier stored on payments.provider.
     */
    public function provider(): string;

    /**
     * Open a hosted checkout session for one attempt.
     *
     * @throws ProviderException when the provider is unreachable or rejects
     */
    public function createCheckoutSession(PaymentRequest $request): PaymentResult;

    /**
     * Cancel an open hosted checkout session (customer abandoned flow).
     */
    public function expireSession(string $providerSessionId): void;

    /**
     * Fetch authoritative intent state for reconciliation (FR-PAY-004).
     */
    public function retrievePaymentIntent(string $providerPaymentId): PaymentStatusSnapshot;

    /**
     * Submit a refund against a captured provider payment.
     */
    public function createRefund(RefundRequest $request): RefundResult;

    /**
     * Verify an inbound webhook signature over the raw body.
     *
     * @param  string  $payload  Raw request body, untouched by framework parsing
     * @param  string  $signatureHeader  Raw provider signature header value
     *
     * @throws WebhookSignatureException on bad payload, bad signature format, or mismatch
     */
    public function verifyWebhook(string $payload, string $signatureHeader): WebhookEvent;
}
