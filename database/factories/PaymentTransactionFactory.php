<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => PaymentFactory::new(),
            'payment_attempt_id' => null,
            'provider' => 'payrex',
            'transaction_type' => 'charge',
            'provider_transaction_id' => 'pay_'.Str::lower(Str::ulid()),
            'amount_minor' => fn (array $attributes) => (int) (Payment::query()->find($attributes['payment_id'])->amount_minor ?? 25000),
            'currency_code' => config('commerce.currency', 'PHP'),
            'status' => 'succeeded',
            'processed_at' => now(),
            'metadata' => null,
        ];
    }
}
