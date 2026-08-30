<?php

namespace App\Services\Accounting;

use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sequential, date-grouped document numbering (Phase 8, FR-ORD-008):
 * INV-20260829-0001, CN-20260829-0001, … The maximum existing number is
 * locked inside the caller's transaction, so parallel issues cannot reuse
 * a sequence slot.
 */
class DocumentNumberGenerator
{
    /**
     * Next invoice number for today.
     */
    public function nextInvoiceNumber(): string
    {
        return $this->next('INV', 'invoice_number', Invoice::query());
    }

    /**
     * Next credit note number for today.
     */
    public function nextCreditNoteNumber(): string
    {
        return $this->next('CN', 'credit_note_number', CreditNote::query());
    }

    /**
     * @param  string  $prefix  INV or CN
     * @param  string  $column  invoice_number or credit_note_number
     * @param  Builder  $query
     */
    protected function next(string $prefix, string $column, $query): string
    {
        // Safe without a dedicated sequence table: the previous MAX slot
        // is row-locked via the transactional caller.
        $today = now()->format('Ymd');
        $pattern = $prefix.'-'.$today.'-%';

        /** @var Model|null $latest */
        $latest = $query
            ->where($column, 'like', $pattern)
            ->orderByDesc($column)
            ->lockForUpdate()
            ->first();

        $next = $latest
            ? ((int) Str::afterLast((string) $latest->getAttribute($column), '-')) + 1
            : 1;

        return sprintf('%s-%s-%04d', $prefix, $today, $next);
    }
}
