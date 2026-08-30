<?php

namespace App\Actions\Payments;

use App\Enums\RefundStatus;
use App\Exceptions\Payments\PaymentStateException;
use App\Exceptions\Payments\RefundExceedsBalanceException;
use App\Jobs\ProcessRefund;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates a pending refund (the outbox row). All invariants hold inside
 * one transaction BEFORE any external request exists; the gateway call
 * itself happens in ProcessRefund, never here (SRS §32/§55, FR-PAY-008).
 *
 * Partial amounts allocate across the selected order lines by largest-
 * remainder apportionment of remaining line totals, so refund_items sums
 * exactly to amount_minor. Admin-initiated refunds are audit-logged.
 */
class CreateRefund
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @param  list<array{item: int, quantity: float|int}>  $items  Optional line allocation [{item: order_item id, quantity}]
     *
     * @throws PaymentStateException
     * @throws RefundExceedsBalanceException
     */
    public function __invoke(
        Payment $payment,
        int $amountMinor,
        string $reason,
        ?string $remarks,
        array $items = [],
        ?User $actor = null,
    ): Refund {
        /** @var list<array{order_item_id: int, quantity: float, amount_minor: int}> $allocations */
        [$allocations, $amountMinor] = DB::transaction(function () use ($payment, $amountMinor, $items): array {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->isPaid()) {
                throw PaymentStateException::forStatus($locked, 'refunded');
            }

            $captured = (int) $locked->transactions()
                ->where('transaction_type', 'charge')
                ->where('status', 'succeeded')
                ->sum('amount_minor');
            $active = $locked->refunds()
                ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Succeeded->value])
                ->sum('amount_minor');
            $remaining = $captured - (int) $active;

            if ($amountMinor > $remaining) {
                throw RefundExceedsBalanceException::forAmount(max(0, $remaining));
            }

            return [$this->allocate($locked, $items, $amountMinor), $amountMinor];
        });

        /** @var Refund $refund */
        $refund = DB::transaction(function () use ($payment, $amountMinor, $reason, $remarks, $allocations): Refund {
            $storedReason = trim($reason.(filled($remarks) ? ': '.trim((string) $remarks) : ''));

            $refund = $payment->refunds()->create([
                'order_id' => $payment->order_id,
                'amount_minor' => $amountMinor,
                'currency_code' => $payment->currency_code,
                'status' => RefundStatus::Pending,
                'reason' => mb_substr($storedReason, 0, 500),
                'requested_at' => now(),
            ]);

            foreach ($allocations as $allocation) {
                $refund->items()->create([
                    'order_item_id' => $allocation['order_item_id'],
                    'quantity' => $allocation['quantity'],
                    'amount_minor' => $allocation['amount_minor'],
                ]);
            }

            return $refund;
        });

        // The outbox hand-off must not fire before the row exists.
        DB::afterCommit(fn () => ProcessRefund::dispatch($refund->getKey())->onQueue(
            (string) config('payments.queue', 'payments'),
        ));

        // Staff-audit the refund creation (FR-ADMIN-006); null actors
        // (internal flows) are tolerated but produce a null actor_user_id.
        ($this->recordAuditLog)($actor, 'refund.created', 'Refund', (int) $refund->getKey(), null, [
            'order_number' => $payment->order?->order_number,
            'amount_minor' => $amountMinor,
            'currency_code' => $refund->currency_code,
            'status' => $refund->status->value,
            'reason' => mb_substr((string) $refund->reason, 0, 500),
        ]);

        return $refund;
    }

    /**
     * Largest-remainder split of the requested amount over selected lines.
     * With no explicit lines, every refundable line participates.
     *
     * @param  list<array{item: int, quantity: float|int}>  $items
     * @return list<array{order_item_id: int, quantity: float, amount_minor: int}>
     */
    protected function allocate(Payment $payment, array $items, int $amountMinor): array
    {
        /** @var Collection<int, OrderItem> $lines */
        $lines = $payment->order()->firstOrFail()->items()
            ->whereRaw('quantity - quantity_refunded - quantity_cancelled > 0')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($items !== []) {
            foreach ($items as $entry) {
                abort_unless($lines->has((int) $entry['item']), 404);
            }
            $selected = collect($items)->map(fn (array $entry) => [
                'line' => $lines[(int) $entry['item']],
                'quantity' => (float) $entry['quantity'],
            ]);
        } else {
            $selected = $lines->values()->map(fn (OrderItem $line) => [
                'line' => $line,
                'quantity' => (float) $line->quantity - (float) $line->quantity_refunded - (float) $line->quantity_cancelled,
            ]);
        }

        // Validate quantities against what each line can still refund.
        foreach ($selected as $entry) {
            /** @var OrderItem $line */
            $line = $entry['line'];
            $refundable = (float) $line->quantity - (float) $line->quantity_refunded - (float) $line->quantity_cancelled;

            if ($entry['quantity'] <= 0 || $entry['quantity'] - $refundable > 1e-9) {
                throw PaymentStateException::forStatus($payment, 'allocated beyond its refundable quantity');
            }
        }

        // Shares by remaining line total; exact integer distribution.
        $remainingTotals = $selected->map(fn (array $entry) => max(0,
            (int) $entry['line']->line_total_minor
            - (int) round(((float) $entry['line']->quantity_refunded + (float) $entry['line']->quantity_cancelled)
                * (float) $entry['line']->unit_price_minor)));

        $totalShare = (int) $remainingTotals->sum();
        $pool = $amountMinor;

        $allocations = $selected->map(function (array $entry, int $i) use ($remainingTotals, $totalShare, &$pool): array {
            $share = $totalShare > 0 && $i < $remainingTotals->count() - 1
                ? (int) floor($amountMinor * ((int) $remainingTotals[$i]) / $totalShare)
                : $pool; // Last line absorbs rounding dust.

            $share = min(max(0, $share), $pool);
            $pool -= $share;

            return [
                'order_item_id' => (int) $entry['line']->getKey(),
                'quantity' => (float) $entry['quantity'],
                'amount_minor' => $share,
            ];
        })->all();

        return $allocations;
    }
}
