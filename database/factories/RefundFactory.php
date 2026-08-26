<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'payment_id' => PaymentFactory::new()->state([
                'status' => PaymentStatus::Paid,
            ]),
            'order_id' => fn (array $attributes) => Payment::query()
                ->find($attributes['payment_id'])
                ?->order_id ?? Order::factory(),
            'provider_refund_id' => null,
            'amount_minor' => fn (array $attributes) => (int) (Payment::query()->find($attributes['payment_id'])->amount_minor ?? 10000),
            'currency_code' => config('commerce.currency', 'PHP'),
            'status' => RefundStatus::Pending,
            'reason' => 'requested_by_customer',
            'requested_by_user_id' => null,
            'requested_at' => now(),
            'processed_at' => null,
        ];
    }

    /**
     * A provider-acknowledged, settled refund.
     */
    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => RefundStatus::Succeeded,
            'provider_refund_id' => 're_'.Str::lower(Str::ulid()),
            'processed_at' => now(),
        ]);
    }
}
