<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
            'user_id' => UserFactory::new(),
            'checkout_session_id' => null,
            'currency_code' => config('commerce.currency', 'PHP'),
            'order_status' => OrderStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal_minor' => 25000,
            'discount_minor' => 0,
            'shipping_minor' => 0,
            'tax_minor' => 0,
            'adjustment_minor' => 0,
            'total_minor' => 25000,
            'customer_email' => strtolower($this->faker->safeEmail()),
            'customer_phone' => $this->faker->optional()->numerify('+639#########'),
            'placed_at' => now(),
            'paid_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * A guest order without an owning account.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null]);
    }

    /**
     * An order owned by a specific customer.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'customer_email' => strtolower($user->email),
        ]);
    }

    /**
     * Move the order into a given lifecycle state.
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => $status,
            'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null,
        ]);
    }
}
