<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Accounting\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Freeze a billed invoice from the immutable order snapshots (Phase 8,
 * FR-ORD-008). Idempotent per order under concurrency: an already-issued
 * invoice is returned untouched, so a replayed PaymentReceived never
 * double-charges the ledger.
 */
class IssueInvoice
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function __invoke(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            $existing = $order->invoices()
                ->where('status', InvoiceStatus::Issued)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $invoice = $order->invoices()->create([
                'invoice_number' => $this->numbers->nextInvoiceNumber(),
                'status' => InvoiceStatus::Issued,
                'currency_code' => (string) $order->currency_code,
                'subtotal_minor' => (int) $order->subtotal_minor,
                'discount_minor' => (int) $order->discount_minor,
                'tax_minor' => (int) $order->tax_minor,
                'shipping_minor' => (int) $order->shipping_minor,
                'total_minor' => (int) $order->total_minor,
                'issued_at' => now(),
            ]);

            $invoice->items()->createMany(
                $order->items->map(fn ($item): array => [
                    'order_item_id' => $item->getKey(),
                    'description' => Str::squish(trim(
                        (string) $item->product_name_snapshot.' '.(string) $item->variant_name_snapshot
                    )),
                    'quantity' => $item->quantity,
                    'unit_price_minor' => (int) $item->unit_price_minor,
                    'tax_minor' => (int) $item->tax_minor,
                    'line_total_minor' => (int) $item->line_total_minor,
                ])->all()
            );

            return $invoice->load('items');
        });
    }
}
