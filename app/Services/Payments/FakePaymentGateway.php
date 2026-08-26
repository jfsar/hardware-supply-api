<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\Payments\ProviderException;
use Illuminate\Support\Str;

/**
 * Credential-free gateway driving full checkout → webhook → refund flows
 * in local/CI environments. Binds whenever no PayRex secret key exists or
 * PAYMENTS_FAKE_MODE is on, so the whole pipeline is exercisable without
 * provider access (Phase 5 Task 2 exit criterion).
 *
 * Determinism knobs (first match wins):
 *  - PaymentRequest metadata key "fake_outcome" = success | fail
 *  - config payments.fake_outcome
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** In-memory session/intent state for retrievePaymentIntent(). */
    private array $intents = [];

    public function __construct(private readonly SignatureVerifier $verifier) {}

    public function provider(): string
    {
        // Simulates the PayRex contract end-to-end, including the
        // payments.provider value written by PlaceOrder.
        return 'payrex';
    }

    public function createCheckoutSession(PaymentRequest $request): PaymentResult
    {
        if ($this->outcome($request) === 'fail') {
            throw ProviderException::unreachable('create_checkout_session');
        }

        $sessionId = 'cs_fake_'.Str::lower(Str::ulid());
        $intentId = 'pi_fake_'.Str::lower(Str::ulid());

        $this->intents[$intentId] = [
            'status' => 'awaiting_payment_method',
            'amount_minor' => $request->amountMinor,
        ];

        return new PaymentResult(
            providerSessionId: $sessionId,
            redirectUrl: $request->successUrl.'?fake_checkout='.$sessionId,
            providerPaymentId: $intentId,
        );
    }

    public function expireSession(string $providerSessionId): void
    {
        // No external state to mutate; the action records the local cancel.
    }

    public function retrievePaymentIntent(string $providerPaymentId): PaymentStatusSnapshot
    {
        $state = $this->intents[$providerPaymentId] ?? null;

        return new PaymentStatusSnapshot(
            intentStatus: (string) ($state['status'] ?? 'awaiting_payment_method'),
            latestPaymentId: isset($state['latest_payment_id'])
                ? (string) $state['latest_payment_id']
                : null,
            latestPaymentStatus: ($state['status'] ?? null) === 'succeeded' ? 'succeeded' : null,
        );
    }

    /**
     * Flip a fake intent's authoritative status from tests/reconciliation.
     */
    public function setIntentStatus(string $providerPaymentId, string $status): void
    {
        if (! isset($this->intents[$providerPaymentId])) {
            return;
        }

        $this->intents[$providerPaymentId]['status'] = $status;

        if ($status === 'succeeded') {
            // The charge resource PayRex would surface on the settled intent.
            $this->intents[$providerPaymentId]['latest_payment_id'] = $this->intents[$providerPaymentId]['latest_payment_id']
                ?? 'pay_fake_'.Str::lower(Str::ulid());
        }
    }

    public function createRefund(RefundRequest $request): RefundResult
    {
        return new RefundResult(
            providerRefundId: 're_fake_'.Str::lower(Str::ulid()),
            status: 'pending',
        );
    }

    public function verifyWebhook(string $payload, string $signatureHeader): WebhookEvent
    {
        // Fake deliveries verify against a known local secret, so HTTP
        // signature/ingestion tests exercise the identical code path as
        // production (forge one with FakePaymentGateway::sign()).
        return $this->verifier->verify($payload, $signatureHeader, self::signingSecret());
    }

    /**
     * Sign a payload exactly like the real t/te/li scheme so signature and
     * ingestion tests exercise one code path against either adapter.
     */
    public static function sign(string $secretKey, string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf(
            't=%d,te=%s,li=%s',
            $timestamp,
            hash_hmac('sha256', $timestamp.'.'.$payload, $secretKey),
            '',
        );
    }

    /**
     * A matching secret for tests that need to forge valid deliveries.
     */
    public static function signingSecret(): string
    {
        return 'whsk_fake_signing_secret';
    }

    private function outcome(PaymentRequest $request): string
    {
        $override = $request->metadata['fake_outcome'] ?? null;

        return is_string($override) && $override !== ''
            ? $override
            : (string) config('payments.fake_outcome', 'success');
    }
}
