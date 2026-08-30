<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One outbound delivery row with its attempt/retry state
 * (FR-NOTIF-004). Never leaks the computed signature or envelope body;
 * it is an operational breadcrumb for merchants only.
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'event_id' => $this->resource->event_id,
            'event_type' => $this->resource->event_type,
            'api_version' => $this->resource->api_version,
            'status' => $this->resource->status->value,
            'attempt_count' => $this->resource->attempt_count,
            'next_attempt_at' => optional($this->resource->next_attempt_at)->toISOString(),
            'delivered_at' => optional($this->resource->delivered_at)->toISOString(),
            'last_http_status' => $this->resource->last_http_status,
            'last_error' => $this->resource->last_error,
            'created_at' => optional($this->resource->created_at)->toISOString(),
        ];
    }
}
