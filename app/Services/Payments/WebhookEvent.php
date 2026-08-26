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
     * The resource snapshot carried by this event. Handles both observed
     * provider layouts: the documented nested form (data.resource.{...})
     * and the raw inline form (snapshot fields sit directly under data,
     * where "resource" is just the string discriminator).
     *
     * @return array<string, mixed>
     */
    public function resource(): array
    {
        return self::extractResource($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extractResource(array $data): array
    {
        if (isset($data['resource']) && is_array($data['resource'])) {
            return $data['resource'];
        }

        // Inline layout: the snapshot itself carries the entity id.
        return isset($data['id']) && is_array($data) ? $data : [];
    }
}
