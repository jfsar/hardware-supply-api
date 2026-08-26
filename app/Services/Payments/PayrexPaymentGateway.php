<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\Payments\ProviderException;
use Payrex\Entities\CheckoutSession;
use Payrex\Entities\PaymentIntent;
use Payrex\Entities\Refund;
use Payrex\PayrexClient;
use Throwable;

/**
 * PayRex adapter (SRS §74). Every provider-specific call sits in this one
 * class; nothing past App\Contracts\PaymentGateway may reference Payrex.
 *
 * Endpoint paths, payload schemas, and the webhook signature scheme were
 * verified against the live PayRex API reference (docs.payrex.com, API
 * v1: /checkout_sessions, /payment_intents/:id, /refunds) — the
 * TODO(payrex-sandbox) markers from the abstract-first draft are retired,
 * and tests/Feature/Payments/PayrexSandboxContractTest keeps them honest
 * whenever sandbox credentials are present.
 */
final class PayrexPaymentGateway implements PaymentGateway
{
    private PayrexClient $client;

    public function __construct(
        string $secretKey,
        private readonly SignatureVerifier $verifier,
    ) {
        $this->client = new PayrexClient($secretKey);
    }

    public function provider(): string
    {
        return 'payrex';
    }

    public function createCheckoutSession(PaymentRequest $request): PaymentResult
    {
        $params = [
            'currency' => $request->currency,
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'payment_methods' => $request->paymentMethods,
            'customer_reference_id' => $request->reference,
            'description' => mb_substr($request->description, 0, 250),
            'line_items' => [
                [
                    // One aggregate line; authoritative totals already live
                    // on the order snapshot and must not diverge (FR-PAY-002).
                    'name' => mb_substr($request->description, 0, 120),
                    'amount' => $request->amountMinor,
                    'quantity' => 1,
                ],
            ],
        ];

        if ($request->metadata !== []) {
            $params['metadata'] = $request->metadata;
        }

        try {
            /** @var CheckoutSession $session */
            $session = $this->client->checkoutSessions->create($params);
        } catch (Throwable $e) {
            report($e);

            throw ProviderException::unreachable('create_checkout_session');
        }

        return new PaymentResult(
            providerSessionId: (string) $session->id,
            redirectUrl: (string) $session->url,
            providerPaymentId: isset($session->payment_intent['id'])
                ? (string) $session->payment_intent['id']
                : null,
        );
    }

    public function expireSession(string $providerSessionId): void
    {
        try {
            $this->client->checkoutSessions->expire($providerSessionId);
        } catch (Throwable $e) {
            report($e);

            throw ProviderException::unreachable('expire_session');
        }
    }

    public function retrievePaymentIntent(string $providerPaymentId): PaymentStatusSnapshot
    {
        try {
            /** @var PaymentIntent $intent */
            $intent = $this->client->paymentIntents->retrieve($providerPaymentId);
        } catch (Throwable $e) {
            report($e);

            throw ProviderException::unreachable('retrieve_payment_intent');
        }

        return new PaymentStatusSnapshot(
            intentStatus: (string) $intent->status,
            latestPaymentId: is_array($intent->latest_payment) && isset($intent->latest_payment['id'])
                ? (string) $intent->latest_payment['id']
                : null,
            latestPaymentStatus: is_array($intent->latest_payment) && isset($intent->latest_payment['status'])
                ? (string) $intent->latest_payment['status']
                : null,
        );
    }

    public function createRefund(RefundRequest $request): RefundResult
    {
        $params = [
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
            'payment_id' => $request->providerPaymentId,
            'reason' => $request->reason,
        ];

        if ($request->remarks !== null && $request->remarks !== '') {
            $params['remarks'] = $request->remarks;
        }

        if ($request->metadata !== []) {
            $params['metadata'] = $request->metadata;
        }

        try {
            /** @var Refund $refund */
            $refund = $this->client->refunds->create($params);
        } catch (Throwable $e) {
            report($e);

            throw ProviderException::unreachable('create_refund');
        }

        return new RefundResult(
            providerRefundId: (string) $refund->id,
            status: (string) $refund->status,
        );
    }

    /**
     * Manual verification per the documented t/te/li scheme. Deliberately
     * not delegated to \Payrex\Webhook::parseEvent(): that helper performs
     * no timestamp freshness check (replay exposure) and its error paths
     * leak vendor exception types past the gateway boundary.
     */
    public function verifyWebhook(string $payload, string $signatureHeader): WebhookEvent
    {
        return $this->verifier->verify($payload, $signatureHeader, (string) config('payments.payrex.webhook_secret'));
    }
}
