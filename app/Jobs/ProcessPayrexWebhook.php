<?php

namespace App\Jobs;

use App\Actions\Payments\SettleRefund;
use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TransactionType;
use App\Enums\WebhookProcessingStatus;
use App\Events\PaymentReceived;
use App\Models\InventoryReservation;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Refund;
use App\Services\Inventory\ConsumeStock;
use App\Services\Inventory\ReleaseStock;
use App\Services\Payments\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies business effects for one verified inbound webhook (Phase 5
 * Task 4 / FR-PAY-004). Idempotent by construction: duplicate deliveries
 * short-circuit on processing state AND on payment/refund state guards,
 * so replays never double-apply financial facts (NFR-REL-004, SRS §69).
 *
 * Provider truth is re-fetched before mutating on success events — a
 * webhook alone never flips money state (FR-PAY-004).
 */
class ProcessPayrexWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 300];

    public function __construct(public readonly int $paymentWebhookId)
    {
        $this->onQueue((string) config('payments.queue', 'payments'));
    }

    public function handle(
        PaymentGateway $gateway,
        ConsumeStock $consumeStock,
        ReleaseStock $releaseStock,
        SettleRefund $settleRefund,
    ): void {
        /** @var PaymentWebhook|null $webhook */
        $webhook = PaymentWebhook::query()->find($this->paymentWebhookId);

        // Deleted mid-flight or already finalized by another worker.
        if ($webhook === null || $webhook->processing_status !== WebhookProcessingStatus::Pending) {
            return;
        }

        try {
            $payload = (array) $webhook->payload;

            match ((string) $webhook->event_type) {
                'payment_intent.succeeded' => $this->applyIntentSucceeded($webhook, $gateway, $consumeStock),
                'checkout_session.expired' => $this->applySessionExpired($webhook, $releaseStock),
                'refund.updated' => $this->applyRefundUpdated($webhook, $settleRefund),
                'refund.created' => $this->markProcessed($webhook), // final status arrives via refund.updated
                default => $this->markProcessed($webhook), // unknown types are recorded, never fatal
            };
        } catch (Throwable $e) {
            // Row stays Pending so queue retries still enter handle();
            // permanent exhaustion lands in failed().
            Log::channel('payments')->warning('Webhook processing attempt failed.', [
                'payment_webhook_id' => $this->paymentWebhookId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // rethrow for queue retry with backoff()
        }
    }

    /**
     * Terminal sink once retries are exhausted (NFR-OBS-003): flag the row
     * Failed so operators can see it and replays stop entering handle().
     */
    public function failed(Throwable $exception): void
    {
        PaymentWebhook::query()
            ->whereKey($this->paymentWebhookId)
            ->where('processing_status', WebhookProcessingStatus::Pending->value)
            ->update([
                'processing_status' => WebhookProcessingStatus::Failed->value,
                'processing_error' => mb_substr($exception->getMessage(), 0, 65535),
            ]);

        Log::channel('payments')->error('Webhook processing permanently failed.', [
            'payment_webhook_id' => $this->paymentWebhookId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Settle the payment only when the provider independently confirms the
     * intent succeeded, then flip payment/order/reservations atomically.
     */
    protected function applyIntentSucceeded(
        PaymentWebhook $webhook,
        PaymentGateway $gateway,
        ConsumeStock $consumeStock,
    ): void {
        $resource = $this->resource($webhook);
        $intentId = (string) ($resource['id'] ?? '');

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('provider', $gateway->provider())
            ->where('provider_payment_id', $intentId)
            ->first();

        if ($payment === null) {
            Log::channel('payments')->warning('Webhook for unknown payment ignored.', [
                'webhook_id' => $webhook->getKey(),
                'intent_id' => $intentId,
            ]);
            $this->markProcessed($webhook);

            return;
        }

        if ($payment->status->isPaid()) {
            $this->markProcessed($webhook);

            return;
        }

        // Server-side verification BEFORE any local mutation (FR-PAY-004).
        $snapshot = $gateway->retrievePaymentIntent($intentId);

        $confirmed = $snapshot->intentStatus === 'succeeded'
            || $snapshot->latestPaymentStatus === 'succeeded';

        if (! $confirmed) {
            Log::channel('payments')->notice('Success webhook not confirmed by provider yet.', [
                'webhook_id' => $webhook->getKey(),
                'intent_id' => $intentId,
                'provider_status' => $snapshot->intentStatus,
            ]);

            throw new \RuntimeException(sprintf(
                'Provider has not confirmed intent %s as succeeded (status: %s); will retry.',
                $intentId,
                $snapshot->intentStatus,
            ));
        }

        [$order] = DB::transaction(function () use ($webhook, $payment, $snapshot, $consumeStock): array {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $order = $locked->order()->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPaid()) {
                $locked->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'metadata' => array_merge(is_array($locked->metadata) ? $locked->metadata : [], array_filter([
                        'provider_latest_payment_id' => $snapshot->latestPaymentId,
                    ], static fn ($v) => $v !== null)),
                ])->save();

                if ($snapshot->latestPaymentId !== null) {
                    $locked->transactions()->create([
                        'provider' => $locked->provider,
                        'transaction_type' => TransactionType::Charge,
                        'provider_transaction_id' => $snapshot->latestPaymentId,
                        'amount_minor' => (int) $locked->amount_minor,
                        'currency_code' => $locked->currency_code,
                        'status' => 'succeeded',
                        'processed_at' => now(),
                    ]);
                }
            }

            foreach ($order->reservations()->get() as $reservation) {
                /** @var InventoryReservation $reservation */
                ($consumeStock)($reservation);
            }

            if ($order->order_status === OrderStatus::AwaitingPayment) {
                $order->forceFill([
                    'order_status' => OrderStatus::Paid,
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ])->save();

                $order->statusHistories()->create([
                    'from_status' => OrderStatus::AwaitingPayment->value,
                    'to_status' => OrderStatus::Paid->value,
                    'changed_by_user_id' => null,
                    'reason' => 'payment_received',
                    'metadata' => ['event' => 'payment_intent.succeeded'],
                ]);
            } elseif ($order->payment_status !== PaymentStatus::Paid) {
                $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();
            }

            $this->markProcessed($webhook);

            return [$order];
        });

        event(new PaymentReceived($order));
    }

    /**
     * Abandoned hosted checkout: expire the payment and unwind the order
     * (AwaitingPayment → Expired) releasing still-held reservations. Stock
     * policy stays owned by ReleaseStock, mirroring CancelOrder semantics
     * without its customer-actor requirements.
     */
    protected function applySessionExpired(PaymentWebhook $webhook, ReleaseStock $releaseStock): void
    {
        $resource = $this->resource($webhook);
        $sessionId = (string) ($resource['id'] ?? '');

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('metadata->provider_session_id', $sessionId)
            ->orderByDesc('id')
            ->first();

        if ($payment === null || $payment->status->isPaid()) {
            if ($payment === null) {
                Log::channel('payments')->warning('Expiry webhook for unknown checkout session ignored.', [
                    'webhook_id' => $webhook->getKey(),
                    'session_id' => $sessionId,
                ]);
            }
            $this->markProcessed($webhook);

            return;
        }

        DB::transaction(function () use ($webhook, $payment, $releaseStock): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $order = $locked->order()->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPaid()) {
                $locked->forceFill(['status' => PaymentStatus::Expired])->save();
            }

            if ($order->order_status === OrderStatus::AwaitingPayment) {
                $order->loadMissing('reservations');

                foreach ($order->reservations as $reservation) {
                    ($releaseStock)($reservation, expired: true);
                }

                $order->forceFill([
                    'order_status' => OrderStatus::Expired,
                    'payment_status' => PaymentStatus::Expired,
                ])->save();

                $order->statusHistories()->create([
                    'from_status' => OrderStatus::AwaitingPayment->value,
                    'to_status' => OrderStatus::Expired->value,
                    'changed_by_user_id' => null,
                    'reason' => 'checkout_session_expired',
                    'metadata' => ['event' => 'checkout_session.expired'],
                ]);
            }

            $this->markProcessed($webhook);
        });
    }

    /**
     * Final refund outcomes arrive here. Settlement (including per-line
     * quantity_refunded bumps) funnels through SettleRefund so the job and
     * webhook paths can never double-apply.
     */
    protected function applyRefundUpdated(PaymentWebhook $webhook, SettleRefund $settleRefund): void
    {
        $resource = $this->resource($webhook);
        $providerRefundId = (string) ($resource['id'] ?? '');
        $providerStatus = (string) ($resource['status'] ?? '');

        /** @var Refund|null $refund */
        $refund = Refund::query()->where('provider_refund_id', $providerRefundId)->first();

        if ($refund === null) {
            Log::channel('payments')->warning('Refund webhook for unknown refund ignored.', [
                'webhook_id' => $webhook->getKey(),
                'provider_refund_id' => $providerRefundId,
            ]);
            $this->markProcessed($webhook);

            return;
        }

        $mapped = match ($providerStatus) {
            'succeeded' => RefundStatus::Succeeded,
            'failed' => RefundStatus::Failed,
            'cancelled' => RefundStatus::Cancelled,
            default => null,
        };

        if ($mapped === null) {
            $this->markProcessed($webhook);

            return;
        }

        DB::transaction(function () use ($refund, $mapped, $settleRefund): void {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($mapped === RefundStatus::Succeeded) {
                // Single idempotency gate: settles lines + aggregates.
                ($settleRefund)($locked);

                return;
            }

            if (! $locked->status->isSettled() && $locked->status === RefundStatus::Pending) {
                $locked->forceFill(['status' => $mapped])->save();
            }

            /** @var Payment $payment */
            $payment = $locked->payment()->lockForUpdate()->firstOrFail();
            ($settleRefund)->refreshAggregate($payment);
        });

        $this->markProcessed($webhook);
    }

    /**
     * The resource snapshot carried by the stored payload. Tolerates the
     * documented nested layout and PayRex's raw inline layout (verified
     * live: data holds id/status directly, "resource" is a string tag).
     *
     * @return array<string, mixed>
     */
    protected function resource(PaymentWebhook $webhook): array
    {
        $data = $webhook->payload['data'] ?? null;

        return is_array($data) ? WebhookEvent::extractResource($data) : [];
    }

    protected function markProcessed(PaymentWebhook $webhook): void
    {
        $webhook->forceFill([
            'processing_status' => WebhookProcessingStatus::Processed,
            'processed_at' => now(),
            'processing_error' => null,
        ])->save();
    }
}
