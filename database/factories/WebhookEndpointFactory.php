<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'name' => $this->faker->company().' webhook',
            'url' => $this->faker->url(),
            'secret_encrypted' => encrypt(Str::random(64)),
            'is_active' => true,
        ];
    }

    /**
     * An endpoint subscribed to the given event types.
     *
     * @param  list<string>  $events
     */
    public function subscribedTo(array $events = ['order.created']): static
    {
        return $this->afterCreating(function (WebhookEndpoint $endpoint) use ($events): void {
            foreach ($events as $event) {
                $endpoint->subscriptions()->firstOrCreate([
                    'event_type' => $event,
                    'api_version' => (string) config('webhooks.api_version', '1.0'),
                ]);
            }
        });
    }
}
