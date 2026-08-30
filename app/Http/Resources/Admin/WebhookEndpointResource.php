<?php

namespace App\Http\Resources\Admin;

use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Webhook endpoint payload (FR-NOTIF-003). The HMAC secret is included
 * exactly once at creation via revealingSecret(); it is never exposed
 * again (NFR-SEC-010).
 *
 * @property WebhookEndpoint $resource
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * The plaintext secret to reveal (only set on the store response).
     */
    public ?string $plainSecret = null;

    /**
     * Mark this resource to expose the one-time secret.
     */
    public function revealingSecret(string $plainSecret): static
    {
        $this->plainSecret = $plainSecret;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, WebhookSubscription> $subscriptions */
        $subscriptions = $this->resource->relationLoaded('subscriptions')
            ? $this->resource->subscriptions
            : $this->resource->subscriptions()->get();

        $payload = [
            'ulid' => $this->resource->ulid,
            'name' => $this->resource->name,
            'url' => $this->resource->url,
            'is_active' => $this->resource->is_active,
            'events' => $subscriptions->pluck('event_type')->unique()->values(),
            'api_version' => $subscriptions->pluck('api_version')->unique()->values()->first() ?? (string) config('webhooks.api_version', '1.0'),
            'created_at' => optional($this->resource->created_at)->toISOString(),
            'updated_at' => optional($this->resource->updated_at)->toISOString(),
        ];

        // The HMAC secret appears exactly once, on the store response.
        if ($this->plainSecret !== null) {
            $payload['secret'] = $this->plainSecret;
        }

        return $payload;
    }
}
