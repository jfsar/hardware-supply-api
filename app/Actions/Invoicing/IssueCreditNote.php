<?php

namespace App\Actions\Invoicing;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Refund;
use App\Services\Accounting\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Raise a credit note against the issuer's invoice when a refund settles
 * (Phase 8). The credit mirrors the settled refund amount exactly; if no
 * issued invoice exists yet the event is skipped with a log line — the
 * ledger should already hold one for gateway-paid flows.
 */
class IssueCreditNote
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    public function __invoke(Refund $refund): ?CreditNote
    {
        return DB::transaction(function () use ($refund): ?CreditNote {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::query()
                ->where('order_id', $refund->order_id)
                ->where('status', InvoiceStatus::Issued)
                ->latest('id')
                ->first();

            if ($invoice === null) {
                Log::warning('No issued invoice to credit for a settled refund.', [
                    'refund_ulid' => $refund->ulid,
                    'order_id' => $refund->order_id,
                ]);

                return null;
            }

            $refund->loadMissing('items.orderItem');

            $creditNote = $invoice->creditNotes()->create([
                'order_id' => (int) $refund->order_id,
                'credit_note_number' => $this->numbers->nextCreditNoteNumber(),
                'status' => CreditNoteStatus::Issued,
                'reason' => $refund->reason,
                'amount_minor' => (int) $refund->amount_minor,
                'currency_code' => (string) $refund->currency_code,
                'issued_at' => now(),
            ]);

            $creditNote->items()->createMany(
                $refund->items->map(function ($item): array {
                    $description = Str::squish(trim(
                        (string) optional($item->orderItem)->product_name_snapshot
                        .' '.(string) optional($item->orderItem)->variant_name_snapshot
                    ));

                    return [
                        'order_item_id' => $item->order_item_id,
                        'description' => $description !== '' ? $description : 'Refunded line',
                        'quantity' => $item->quantity,
                        'amount_minor' => (int) $item->amount_minor,
                    ];
                })->all()
            );

            return $creditNote->load('items');
        });
    }
}
