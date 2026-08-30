<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Fans a domain event out to every active, subscribed endpoint and
 * persists the delivery outbox row (FR-NOTIF-003). Envelope bodies are
 * JSON-encoded deterministically so the HMAC signature computed here is
 * byte-for-byte identical to the body the DeliverWebhook job sends later.
 */
class WebhookDispatcher
{
    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public const HMAC_ALGORITHM = 'sha256';

    /**
     * Find all live endpoints subscribed to $eventType at the configured
     * api_version and queue a delivery for each.
     *
     * @param  array<string, mixed>  $payload
     * @param  non-empty-string  $eventType
     */
    public function dispatch(string $eventType, array $payload, ?string $eventId = null): void
    {
        $eventId ??= (string) Str::ulid();
        $apiVersion = (string) config('webhooks.api_version', '1.0');

        WebhookEndpoint::query()
            ->where('is_active', true)
            ->whereHas('subscriptions', function (Builder $query) use ($eventType, $apiVersion): void {
                $query->where('event_type', $eventType)
                    ->where('api_version', $apiVersion);
            })
            ->get()
            ->each(function (WebhookEndpoint $endpoint) use ($eventType, $payload, $eventId, $apiVersion): void {
                $this->dispatchToEndpoint($endpoint, $eventType, $payload, $eventId, $apiVersion);
            });
    }

    /**
     * Create or refresh the (endpoint, event) delivery row and queue the
     * HTTP attempt. Delivered is terminal: replayed domain events are
     * deduplicated so subscribers keep the same Idempotency-Key.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchToEndpoint(
        WebhookEndpoint $endpoint,
        string $eventType,
        array $payload,
        string $eventId,
        string $apiVersion,
    ): void {
        $delivery = WebhookDelivery::query()->firstOrNew([
            'webhook_endpoint_id' => $endpoint->getKey(),
            'event_id' => $eventId,
        ]);

        if ($delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $envelope = [
            'id' => $eventId,
            'event' => $eventType,
            'api_version' => $apiVersion,
            'timestamp' => now()->toISOString(),
            'payload' => $payload,
        ];

        $body = json_encode($envelope, self::JSON_FLAGS | JSON_THROW_ON_ERROR);
        $signature = $this->sign($endpoint, $body);

        $delivery->forceFill([
            'event_type' => $eventType,
            'api_version' => $apiVersion,
            'payload' => $envelope,
            'signature' => $signature,
            'status' => WebhookDeliveryStatus::Pending,
            'next_attempt_at' => null,
            'last_http_status' => null,
            'last_error' => null,
        ]);

        // A repeated domain event after exhaustion gets a fresh retry
        // budget, matching "one delivery row per event id".
        if ($delivery->isDirty('status') && $delivery->status === WebhookDeliveryStatus::Pending) {
            $delivery->attempt_count = 0;
        }

        $delivery->save();

        DeliverWebhook::dispatch($delivery->getKey(), $signature)
            ->onQueue((string) config('webhooks.queue', 'webhooks'));
    }

    /**
     * Deterministic HMAC-SHA256 over the exact envelope body.
     */
    public function sign(WebhookEndpoint $endpoint, string $body): string
    {
        $secret = decrypt($endpoint->secret_encrypted);

        return hash_hmac(self::HMAC_ALGORITHM, $body, (string) $secret);
    }
}
