<?php

namespace App\Console\Commands;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\ProcessRefund;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aligns local payment/refund state with provider truth (Phase 5 Task 6,
 * FR-PAY-004). PayRex emits no "failed" event, so this sweep IS the
 * failure detector: rows stuck Processing past the threshold are
 * re-fetched and resolved. Safe to run repeatedly.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile
        {--stuck-minutes=30 : Age threshold before a row is considered stuck}
        {--limit=100 : Maximum payments to reconcile per run}';

    protected $description = 'Reconcile gateway payments and refunds against provider truth';

    public function handle(PaymentGateway $gateway): int
    {
        $stuckBefore = now()->subMinutes(max(1, (int) $this->option('stuck-minutes')));
        $drift = 0;

        Payment::query()
            ->where('provider', $gateway->provider())
            ->whereNotNull('provider_payment_id')
            ->where('status', PaymentStatus::Processing->value)
            ->where('last_attempt_at', '<', $stuckBefore)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->each(function (Payment $payment) use ($gateway, &$drift): void {
                try {
                    $snapshot = $gateway->retrievePaymentIntent((string) $payment->provider_payment_id);
                } catch (\Throwable $e) {
                    $this->warn("payment {$payment->ulid}: provider unreachable ({$e->getMessage()})");

                    return;
                }

                // Success effects (stock consumption, confirmation email)
                // belong to the webhook path; when its event never arrives,
                // reconciliation settles the financial rows directly.
                if ($snapshot->intentStatus === 'succeeded') {
                    $this->settleFromProviderTruth($payment);
                    $drift++;

                    return;
                }

                if (in_array($snapshot->intentStatus, ['canceled', 'cancelled'], true)) {
                    $this->alignTerminal($payment, PaymentStatus::Cancelled);
                    $this->line("payment {$payment->ulid}: aligned to cancelled");
                    $drift++;
                }

                // awaiting/processing intents are simply left for later sweeps.
            });

        // Refunds stuck pending with no provider reference get one more kick.
        Refund::query()
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('provider_refund_id')
            ->where('requested_at', '<', $stuckBefore)
            ->orderBy('id')
            ->limit(50)
            ->each(function (Refund $refund) use (&$drift): void {
                ProcessRefund::dispatch($refund->getKey())->onQueue(
                    (string) config('payments.queue', 'payments'),
                );

                $this->line("refund {$refund->ulid}: re-dispatched");
                $drift++;
            });

        Log::channel('payments')->info('payments:reconcile complete.', ['drift' => $drift]);

        $this->info("Reconciliation finished. Drifted rows: {$drift}");

        return self::SUCCESS;
    }

    /**
     * Provider says succeeded but no event was processed: settle the
     * payment and order rows from provider truth.
     */
    protected function settleFromProviderTruth(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPaid()) {
                $locked->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => $locked->paid_at ?? now(),
                ])->save();

                $order = $locked->order()->lockForUpdate()->firstOrFail();

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
                        'reason' => 'reconciled_from_provider',
                        'metadata' => ['source' => 'payments:reconcile'],
                    ]);
                }
            }
        });

        $this->line("payment {$payment->ulid}: settled from provider truth");
    }

    /**
     * Align a non-paid row onto a terminal provider state.
     */
    protected function alignTerminal(Payment $payment, PaymentStatus $status): void
    {
        DB::transaction(function () use ($payment, $status): void {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPaid()) {
                $locked->forceFill(['status' => $status, 'failed_at' => now()])->save();
            }
        });
    }
}
