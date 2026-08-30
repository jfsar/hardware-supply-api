<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Performs one outbound webhook POST (FR-NOTIF-004). Retries follow the
 * configured schedule; each attempt redelivers the exact persisted
 * envelope with the same HMAC signature and Idempotency-Key so
 * subscribers can de-duplicate (FR-NOTIF-005). Queue-level retries are
 * disabled (tries = 1) — the delivery row drives its own backoff.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $timeout = 25;

    public int $tries = 1;

    public function __construct(
        public int $deliveryId,
        public string $signature,
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->findOrFail($this->deliveryId);

        if ($delivery->status->isFinal()) {
            return;
        }

        /** @var WebhookEndpoint|null $endpoint */
        $endpoint = $delivery->endpoint;

        $body = json_encode($delivery->payload, WebhookDispatcher::JSON_FLAGS);

        try {
            // Send the stored envelope verbatim (byte-for-byte) so the
            // subscriber's recomputed HMAC matches X-Signature.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'HardwareSupply-Webhooks/'.(string) config('webhooks.api_version', '1.0'),
                'X-Signature' => 'sha256='.$this->signature,
                'Idempotency-Key' => $delivery->event_id,
            ])
                ->withBody($body, 'application/json')
                ->timeout((int) config('webhooks.http_timeout', 5))
                ->withoutRedirecting()
                ->post($endpoint->url);

            // getStatusCode() throws on connection errors, so reaching
            // here means we have a real HTTP response.
            $status = $response->status();

            if ($status >= 200 && $status < 300) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Delivered,
                    'attempt_count' => $delivery->attempt_count + 1,
                    'delivered_at' => now(),
                    'last_http_status' => $status,
                    'last_error' => null,
                    'next_attempt_at' => null,
                ])->save();

                return;
            }

            $this->scheduleRetry($delivery, $status, 'HTTP '.$status);
        } catch (Throwable $throwable) {
            $this->scheduleRetry($delivery, null, mb_substr($throwable->getMessage(), 0, 490));
        }
    }

    /**
     * Advance the retry schedule; when the budget is spent the delivery
     * becomes Exhausted. Returns true when another attempt was queued.
     */
    protected function scheduleRetry(
        WebhookDelivery $delivery,
        ?int $httpStatus,
        string $error,
    ): bool {
        $schedule = (array) config('webhooks.retry_schedule', []);
        $span = $delivery->attempt_count + 1;

        if (empty($schedule) || $span >= count($schedule)) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Exhausted,
                'attempt_count' => $delivery->attempt_count + 1,
                'last_http_status' => $httpStatus,
                'last_error' => mb_substr($error, 0, 490),
                'next_attempt_at' => null,
            ])->save();

            return false;
        }

        $delay = (int) $schedule[$span];

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'attempt_count' => $delivery->attempt_count + 1,
            'last_http_status' => $httpStatus,
            'last_error' => mb_substr($error, 0, 490),
            'next_attempt_at' => now()->addSeconds($delay),
        ])->save();

        DeliverWebhook::dispatch($delivery->getKey(), $this->signature)
            ->delay($delay)
            ->onQueue((string) config('webhooks.queue', 'webhooks'));

        return true;
    }
}
