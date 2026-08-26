<?php

namespace Database\Factories;

use App\Enums\WebhookProcessingStatus;
use App\Models\PaymentWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentWebhook>
 */
class PaymentWebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $intentId = 'pi_'.Str::lower(Str::ulid());

        return [
            'provider' => 'payrex',
            'provider_event_id' => 'evt_'.Str::lower(Str::ulid()),
            'event_type' => 'payment_intent.succeeded',
            'signature_valid' => true,
            'payload' => [
                'id' => 'evt_placeholder',
                'type' => 'payment_intent.succeeded',
                'livemode' => false,
                'data' => [
                    'resource' => [
                        'id' => $intentId,
                        'resource' => 'payment_intent',
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'headers' => null,
            'processing_status' => WebhookProcessingStatus::Pending,
            'received_at' => now(),
            'processed_at' => null,
            'processing_error' => null,
        ];
    }

    /**
     * Keep the payload envelope id consistent with the event id column.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PaymentWebhook $webhook): void {
            $webhook->payload = array_merge((array) $webhook->payload, [
                'id' => $webhook->provider_event_id,
            ]);
        });
    }
}
