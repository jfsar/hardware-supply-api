<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use App\Services\RecordAuditLog;

/**
 * Attach a staff note to an order (Phase 8, FR-ADMIN-006). Notes flagged
 * customer-visible flow into the customer-facing OrderResource; internal
 * notes remain staff-only.
 *
 * @param  array{note: string, is_customer_visible?: bool}  $data
 */
class AddOrderNote
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    public function __invoke(Order $order, User $actor, array $data): OrderNote
    {
        $note = $order->notes()->create([
            'user_id' => $actor->getKey(),
            'note' => $data['note'],
            'is_customer_visible' => (bool) ($data['is_customer_visible'] ?? false),
        ]);

        ($this->recordAuditLog)($actor, 'order.note_added', 'OrderNote', (int) $note->getKey(), null, [
            'order_number' => $order->order_number,
            'is_customer_visible' => (bool) ($data['is_customer_visible'] ?? false),
        ]);

        return $note->load('author:id,first_name,last_name');
    }
}
