<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'payment_attempt_id', 'provider', 'transaction_type',
    'provider_transaction_id', 'amount_minor', 'currency_code', 'status',
    'processed_at', 'metadata',
])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<Database\Factories\PaymentTransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'amount_minor' => 'integer',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The settled payment this financial fact belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
