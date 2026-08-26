<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'order_id' => OrderFactory::new(),
            'provider' => 'internal',
            'payment_method' => 'cod',
            'currency_code' => config('commerce.currency', 'PHP'),
            'amount_minor' => 25000,
            'status' => PaymentStatus::Pending,
            'provider_payment_id' => null,
            'last_attempt_at' => now(),
            'paid_at' => null,
            'failed_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Bind to a specific order and amount.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'amount_minor' => $order->total_minor,
            'currency_code' => $order->currency_code,
        ]);
    }
}
