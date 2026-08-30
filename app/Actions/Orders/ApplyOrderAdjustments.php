<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Append-only manual price adjustments (Phase 8, SRS §69): each PATCH
 * adds fresh adjustment rows tagged with the acting admin and a signed
 * amount, then recomputes the order totals under a row lock so the
 * invariant `total == Σ lines + shipping + tax − adjustments` always
 * holds. Prior rows are never mutated or deleted — the order's own
 * adjustment rows ARE the immutable history.
 */
class ApplyOrderAdjustments
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @param  list<array{type: string, label: string, amount_minor: int, reason?: string|null}>  $adjustments
     *
     * @throws ValidationException when the order cannot be adjusted
     */
    public function __invoke(Order $order, User $actor, array $adjustments): Order
    {
        return DB::transaction(function () use ($order, $actor, $adjustments): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->order_status, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'adjustments' => __('Cancelled or expired orders cannot be adjusted.'),
                ]);
            }

            foreach ($adjustments as $entry) {
                $locked->adjustments()->create([
                    'type' => (string) $entry['type'],
                    'label' => (string) $entry['label'],
                    'amount_minor' => (int) $entry['amount_minor'],
                    'currency_code' => $locked->currency_code,
                    'reason' => $entry['reason'] ?? null,
                    'created_by_user_id' => $actor->getKey(),
                ]);
            }

            $adjustmentTotal = (int) OrderAdjustment::query()
                ->where('order_id', $locked->getKey())
                ->sum('amount_minor');

            $newTotal = (int) $locked->subtotal_minor
                + (int) $locked->shipping_minor
                + (int) $locked->tax_minor
                - (int) $locked->discount_minor
                + $adjustmentTotal;

            $oldValues = $locked->getAttributes();

            $locked->forceFill([
                'adjustment_minor' => $adjustmentTotal,
                'total_minor' => $newTotal,
            ])->save();

            $locked->statusHistories()->create([
                'from_status' => $locked->order_status->value,
                'to_status' => $locked->order_status->value,
                'changed_by_user_id' => $actor->getKey(),
                'reason' => 'adjustment',
                'metadata' => [
                    'adjustment_count' => count($adjustments),
                    'adjustment_minor' => $adjustmentTotal,
                    'total_minor' => $newTotal,
                ],
            ]);

            ($this->recordAuditLog)($actor, 'order.adjustments_applied', 'Order', (int) $locked->getKey(), $oldValues);

            return $locked->load('adjustments');
        });
    }
}
