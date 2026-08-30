<?php

namespace App\Listeners;

use App\Actions\Invoicing\IssueCreditNote;
use App\Enums\RefundStatus;
use App\Events\RefundSucceeded;

/**
 * When a refund settles, raise the matching credit note against the
 * order's invoice. RefundSucceeded fires exactly once per refund
 * (SettleRefund idempotency gate), preserving ledger integrity.
 */
class IssueCreditNoteOnRefundSucceeded
{
    public function __construct(private readonly IssueCreditNote $issueCreditNote) {}

    public function handle(RefundSucceeded $event): void
    {
        if ($event->refund->status !== RefundStatus::Succeeded) {
            return;
        }

        ($this->issueCreditNote)($event->refund);
    }
}
