<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'attempt_number', 'provider_reference', 'request_id', 'status',
    'amount_minor', 'currency_code', 'failure_code', 'failure_message',
    'request_payload', 'response_payload', 'started_at', 'completed_at',
])]
class PaymentAttempt extends Model
{
    /** @use HasFactory<Database\Factories\PaymentAttemptFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'amount_minor' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The parent payment this append-only attempt belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
