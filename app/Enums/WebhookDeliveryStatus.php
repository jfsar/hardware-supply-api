<?php

namespace App\Enums;

/**
 * Delivery lifecycle of one outbound webhook attempt stream (Phase 8,
 * FR-NOTIF-003). A single logical delivery is retried through the whole
 * retry schedule before being marked Exhausted.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Exhausted = 'exhausted';

    /**
     * Whether the delivery should no longer be retried automatically.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
