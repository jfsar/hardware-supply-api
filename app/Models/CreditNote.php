<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A credit note raised against an invoice when a refund settles (Phase 8).
 * Amounts mirror the settled refund; status flips to Void for corrections.
 */
#[Fillable([
    'invoice_id', 'order_id', 'credit_note_number', 'status', 'reason',
    'amount_minor', 'currency_code', 'issued_at', 'pdf_path',
])]
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (CreditNote $creditNote): void {
            $creditNote->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'amount_minor' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The invoice being credited.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The order the credit belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The per-line credit allocation rows.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
