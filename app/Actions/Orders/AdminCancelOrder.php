<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\RecordAuditLog;

/**
 * Admin order cancellation (Phase 8, FR-ADMIN-005): reuses the Phase 4
 * CancelOrder action with the admin provenance tag and superimposes the
 * staff audit row (FR-ADMIN-006).
 */
class AdminCancelOrder
{
    public function __construct(
        protected CancelOrder $cancelOrder,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    public function __invoke(Order $order, User $actor, string $reason): Order
    {
        $oldValues = $order->getAttributes();

        $cancelled = ($this->cancelOrder)($order, $actor, $reason, 'admin');

        ($this->recordAuditLog)($actor, 'order.cancelled', 'Order', (int) $cancelled->getKey(), $oldValues, [
            'order_number' => $cancelled->order_number,
            'order_status' => $cancelled->order_status->value,
            'reason' => $reason,
        ]);

        return $cancelled;
    }
}
