<?php

namespace App\Services\Payments;

/**
 * Verified inbound provider event. The payload is the raw decoded event
 * envelope; data holds the resource snapshot under data.resource.
 */
final class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly bool $livemode,
        public readonly array $data,
        public readonly array $payload,
    ) {}

    /**
     * The resource snapshot carried by this event (e.g. a PaymentIntent hash).
     *
     * @return array<string, mixed>
     */
    public function resource(): array
    {
        $resource = $this->data['resource'] ?? null;

        return is_array($resource) ? $resource : [];
    }
}
