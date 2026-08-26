<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => PaymentFactory::new(),
            'attempt_number' => 1,
            'provider_reference' => null,
            'request_id' => null,
            'status' => AttemptStatus::Pending,
            'amount_minor' => fn (array $attributes) => (int) (Payment::query()->find($attributes['payment_id'])->amount_minor ?? 25000),
            'currency_code' => config('commerce.currency', 'PHP'),
            'failure_code' => null,
            'failure_message' => null,
            'request_payload' => null,
            'response_payload' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * A completed session-creation attempt.
     */
    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Succeeded,
            'provider_reference' => 'cs_fake_'.fake()->regexify('[a-z0-9]{20}'),
            'response_payload' => ['session_id' => 'cs_fake_x', 'intent_id' => 'pi_fake_x'],
            'completed_at' => now(),
        ]);
    }

    /**
     * A failed transport attempt.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Failed,
            'failure_code' => 'provider_unavailable',
            'failure_message' => 'Simulated failure.',
            'completed_at' => now(),
        ]);
    }
}
