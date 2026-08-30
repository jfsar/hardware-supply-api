<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event type an endpoint subscribes to at a given api_version.
 * (Phase 8, FR-NOTIF-003). The endpoint_event_api_version triplet is
 * unique, so subscribing twice is an idempotent upsert.
 */
#[Fillable(['webhook_endpoint_id', 'event_type', 'api_version'])]
class WebhookSubscription extends Model
{
    public const UPDATED_AT = null;

    /**
     * The owning endpoint.
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
