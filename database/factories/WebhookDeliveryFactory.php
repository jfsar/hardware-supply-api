<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpointFactory::new(),
            'event_id' => (string) Str::ulid(),
            'event_type' => 'order.created',
            'api_version' => (string) config('webhooks.api_version', '1.0'),
            'payload' => ['event_type' => 'order.created', 'api_version' => '1.0'],
            'signature' => 'sha256='.hash_hmac('sha256', (string) json_encode([]), 'test-secret'),
            'status' => WebhookDeliveryStatus::Pending,
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'last_http_status' => null,
            'last_error' => null,
        ];
    }

    /**
     * A delivery to the given endpoint for a specific event.
     */
    public function forEndpoint(int $endpointId, string $eventId, string $eventType): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_endpoint_id' => $endpointId,
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
    }
}
