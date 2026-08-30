<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One logical outbound webhook delivery with its full retry stream
 * (Phase 8, FR-NOTIF-004). The (endpoint_id, event_id) pair is unique so
 * a redelivered domain event reuses the same row and envelope
 * idempotency is preserved for subscribers.
 */
#[Fillable([
    'webhook_endpoint_id', 'event_id', 'event_type', 'api_version', 'payload',
    'signature', 'status', 'attempt_count', 'next_attempt_at', 'delivered_at',
    'last_http_status', 'last_error',
])]
class WebhookDelivery extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'payload' => 'array',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_http_status' => 'integer',
        ];
    }

    /**
     * The receiving endpoint.
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
