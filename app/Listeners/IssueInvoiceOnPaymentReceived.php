<?php

namespace App\Listeners;

use App\Actions\Invoicing\IssueInvoice;
use App\Events\PaymentReceived;

/**
 * On first verified settlement of a gateway payment, freeze the order's
 * invoice (FR-ORD-008). COD settlement invoicing is deferred.
 */
class IssueInvoiceOnPaymentReceived
{
    public function __construct(private readonly IssueInvoice $issueInvoice) {}

    public function handle(PaymentReceived $event): void
    {
        ($this->issueInvoice)($event->order);
    }
}
