<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A gateway payment for this order has settled (webhook-verified).
 */
class PaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
