<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\AttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Payments\PaymentBackoffException;
use App\Exceptions\Payments\PaymentMaxAttemptsException;
use App\Exceptions\Payments\PaymentStateException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentRequest;
use Illuminate\Support\Facades\DB;

/**
 * Opens (or re-opens) the hosted checkout flow for a gateway payment
 * (Phase 5 Task 3 / FR-PAY-005/007).
 *
 * Ordering guarantees:
 *  - every try appends a NEW payment_attempts row (max+1); history is
 *    never mutated;
 *  - the provider call happens BETWEEN two short transactions — never
 *    inside one (SRS §32);
 *  - retries respect attempts.max and exponential backoff.
 *
 * @phpstan-type CreateResult array{payment: Payment, redirect_url: string}
 */
class CreateGatewayPayment
{
    public function __construct(
        protected PaymentGateway $gateway,
    ) {}

    /**
     * @return array{payment: Payment, redirect_url: string}
     *
     * @throws PaymentStateException when the row cannot start/retry a session
     * @throws PaymentMaxAttemptsException when attempts are exhausted
     * @throws PaymentBackoffException inside the retry backoff window
     */
    public function __invoke(Payment $payment): array
    {
        $this->assertRetryable($payment);

        $attemptCount = $payment->attempts()->count();
        $max = max(1, (int) config('payments.attempts.max', 3));

        if ($attemptCount >= $max) {
            throw PaymentMaxAttemptsException::reached($max);
        }

        $this->assertBackoffElapsed($payment, $attemptCount);

        /** @var PaymentAttempt $attempt */
        $attempt = DB::transaction(function () use ($payment, $max): PaymentAttempt {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            // Re-check under lock: a concurrent attempt may have won.
            $count = $payment->attempts()->count();
            if ($count >= $max) {
                throw PaymentMaxAttemptsException::reached($max);
            }
            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                throw PaymentStateException::forStatus($payment, 'started');
            }

            $payment->forceFill([
                'status' => PaymentStatus::Processing,
                'last_attempt_at' => now(),
            ])->save();

            return $payment->attempts()->create([
                'attempt_number' => $count + 1,
                'status' => AttemptStatus::Pending,
                'amount_minor' => (int) $payment->amount_minor,
                'currency_code' => $payment->currency_code,
                'request_payload' => ['provider' => $this->gateway->provider()],
                'started_at' => now(),
            ]);
        });

        try {
            $result = ($this->gateway)->createCheckoutSession($this->buildRequest($payment));
        } catch (\Throwable $e) {
            $this->failAttempt($payment, $attempt, $e);

            throw $e;
        }

        DB::transaction(function () use ($payment, $attempt, $result): void {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $attempt->forceFill([
                'status' => AttemptStatus::Succeeded,
                'provider_reference' => $result->providerSessionId,
                'response_payload' => array_filter([
                    'session_id' => $result->providerSessionId,
                    'intent_id' => $result->providerPaymentId,
                ], static fn ($v) => $v !== null),
                'completed_at' => now(),
            ])->save();

            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $metadata['provider_session_id'] = $result->providerSessionId;
            $metadata['checkout_url'] = $result->redirectUrl;

            $payment->forceFill([
                'provider_payment_id' => $payment->provider_payment_id ?? $result->providerPaymentId,
                'metadata' => $metadata,
            ])->save();
        });

        return [
            'payment' => $payment->refresh(),
            'redirect_url' => $result->redirectUrl,
        ];
    }

    /**
     * COD rows and terminal/settled states can never open a session. A
     * Processing row may continue ONLY when its previous attempt died
     * before any provider reference was stored (crash recovery).
     *
     * @throws PaymentStateException
     */
    protected function assertRetryable(Payment $payment): void
    {
        if ($payment->method() === PaymentMethod::Cod) {
            throw PaymentStateException::forStatus($payment, 'processed through the gateway');
        }

        /** @var PaymentStatus $status */
        $status = $payment->status;

        $ok = in_array($status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired], true)
            || (
                $status === PaymentStatus::Processing
                && filled($payment->attempts()->orderByDesc('attempt_number')->value('provider_reference')) === false
            );

        if (! $ok || $status->isPaid()) {
            throw PaymentStateException::forStatus($payment, 'retried');
        }
    }

    /**
     * Exponential backoff between failed attempts, capped at the last tier.
     *
     * @throws PaymentBackoffException
     */
    protected function assertBackoffElapsed(Payment $payment, int $attemptCount): void
    {
        $lastFailedAt = $payment->attempts()
            ->where('status', AttemptStatus::Failed->value)
            ->orderByDesc('attempt_number')
            ->value('completed_at');

        if ($lastFailedAt === null) {
            return;
        }

        $backoff = (array) config('payments.attempts.backoff', [30]);
        $tier = (int) $backoff[min(max($attemptCount, 1), count($backoff)) - 1];
        $elapsedSeconds = (int) $lastFailedAt->diffInSeconds(now(), false);

        if ($elapsedSeconds < $tier) {
            throw PaymentBackoffException::active($tier - $elapsedSeconds);
        }
    }

    protected function buildRequest(Payment $payment): PaymentRequest
    {
        /** @var Order $order */
        $order = $payment->order()->firstOrFail();

        return new PaymentRequest(
            reference: (string) $payment->ulid,
            amountMinor: (int) $payment->amount_minor,
            currency: (string) $payment->currency_code,
            description: __('Order :number', ['number' => $order->order_number]),
            paymentMethods: array_values((array) config('payments.methods.'.$payment->method()->value, [])),
            successUrl: str_replace('{order}', (string) $order->ulid, (string) config('payments.redirect_urls.success')),
            cancelUrl: (string) config('payments.redirect_urls.cancel'),
            metadata: [
                'payment_ulid' => (string) $payment->ulid,
                'order_number' => (string) $order->order_number,
            ],
        );
    }

    /**
     * Record the failed attempt; the payment itself only becomes Failed
     * once attempts are exhausted, otherwise it stays retryable-Pending.
     */
    protected function failAttempt(Payment $payment, PaymentAttempt $attempt, \Throwable $e): void
    {
        DB::transaction(function () use ($payment, $attempt, $e): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $attempt->forceFill([
                'status' => AttemptStatus::Failed,
                'failure_code' => 'provider_unavailable',
                'failure_message' => mb_substr($e->getMessage(), 0, 500),
                'completed_at' => now(),
            ])->save();

            $exhausted = $locked->attempts()->count() >= max(1, (int) config('payments.attempts.max', 3));

            $locked->forceFill([
                'status' => $exhausted ? PaymentStatus::Failed : PaymentStatus::Pending,
                'failed_at' => $exhausted ? now() : $locked->failed_at,
            ])->save();
        });
    }
}
