<?php

namespace App\Events;

use App\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A refund has settled to Succeeded exactly once (fired from SettleRefund's
 * idempotency gate). Listeners raise the credit note and fan out the
 * refund.completed outbound webhook.
 */
class RefundSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Refund $refund) {}
}
