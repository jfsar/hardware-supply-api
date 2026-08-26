<?php

namespace App\Models;

use App\Enums\PaymentMethod as PaymentMethodEnum;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'order_id', 'provider', 'payment_method', 'currency_code', 'amount_minor', 'status',
    'provider_payment_id', 'last_attempt_at', 'paid_at', 'failed_at', 'metadata',
])]
#[Hidden(['provider_payment_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->ulid ??= (string) Str::ulid();
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
            'status' => PaymentStatus::class,
            'metadata' => 'array',
            'last_attempt_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The charged order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Append-only gateway attempts, newest last (FR-PAY-005).
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderBy('attempt_number');
    }

    /**
     * Settled financial facts (charges/refunds).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Refunds drawn against this payment.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * The method enum value for this payment row.
     */
    public function method(): PaymentMethodEnum
    {
        return PaymentMethodEnum::from($this->payment_method);
    }
}
