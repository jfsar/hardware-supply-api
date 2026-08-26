<?php

namespace App\Jobs;

use App\Actions\Payments\SettleRefund;
use App\Contracts\PaymentGateway;
use App\Enums\RefundStatus;
use App\Exceptions\Payments\ProviderException;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Outbox worker: submits one pending refund to the provider and records
 * the outcome (Phase 5 Task 5). The provider call happens strictly
 * outside any local transaction. Final settlement flows through the
 * refund.updated webhook via SettleRefund; when the provider reports a
 * terminal status inline we settle immediately too — SettleRefund is the
 * single idempotency gate.
 */
class ProcessRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [15, 60, 300, 900];

    public function __construct(public readonly int $refundId)
    {
        $this->onQueue((string) config('payments.queue', 'payments'));
    }

    public function handle(PaymentGateway $gateway): void
    {
        /** @var Refund|null $refund */
        $refund = Refund::query()->find($this->refundId);

        if ($refund === null || $refund->status !== RefundStatus::Pending) {
            return;
        }

        /** @var Payment $payment */
        $payment = $refund->payment()->firstOrFail();

        try {
            $result = ($gateway)->createRefund(new RefundRequest(
                providerPaymentId: (string) ($payment->transactions()
                    ->where('transaction_type', 'charge')
                    ->where('status', 'succeeded')
                    ->orderByDesc('id')
                    ->value('provider_transaction_id') ?? $payment->provider_payment_id),
                amountMinor: (int) $refund->amount_minor,
                currency: (string) $refund->currency_code,
                reason: $this->providerReason((string) $refund->reason),
                remarks: null,
                metadata: ['refund_ulid' => (string) $refund->ulid],
            ));
        } catch (ProviderException $e) {
            // Keep Pending: retriable transport failure, queue backoff applies.
            throw $e;
        }

        $refund->forceFill(['provider_refund_id' => $result->providerRefundId])->save();

        if ($result->status === 'succeeded') {
            app(SettleRefund::class)($refund);
        }
    }

    /**
     * Terminal failure sink: stop retrying an explicitly rejected refund.
     */
    public function failed(\Throwable $exception): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund !== null && $refund->status === RefundStatus::Pending) {
            $refund->forceFill(['status' => RefundStatus::Failed])->save();
        }

        Log::channel('payments')->error('Refund processing failed permanently.', [
            'refund_id' => $this->refundId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Internal reason slug → provider reason constant.
     */
    protected function providerReason(string $internalReason): string
    {
        $map = (array) config('payments.refunds.reasons', []);

        return (string) ($map[$internalReason] ?? 'others');
    }
}
