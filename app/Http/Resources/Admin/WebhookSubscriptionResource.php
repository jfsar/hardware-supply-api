<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One endpoint↔event subscription pair (FR-NOTIF-003).
 */
class WebhookSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_type' => $this->resource->event_type,
            'api_version' => $this->resource->api_version,
            'created_at' => optional($this->resource->created_at)->toISOString(),
        ];
    }
}
