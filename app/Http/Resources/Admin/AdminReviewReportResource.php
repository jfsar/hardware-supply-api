<?php

namespace App\Http\Resources\Admin;

use App\Models\ReviewReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One open moderation report in the review queue.
 *
 * @property ReviewReport $resource
 */
class AdminReviewReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'reason_code' => $this->resource->reason_code,
            'details' => $this->resource->details,
            'status' => $this->resource->status->value,
            'created_at' => $this->resource->created_at?->toISOString(),
            'reporter' => $this->whenLoaded('reporter', fn (): ?array => $this->resource->reporter === null ? null : [
                'id' => $this->resource->reporter->ulid,
                'name' => trim(($this->resource->reporter->first_name ?? '').' '.($this->resource->reporter->last_name ?? '')),
                'email' => $this->resource->reporter->email,
            ]),
            'review' => $this->whenLoaded('review', fn (): ?array => $this->resource->review === null ? null : [
                'ulid' => $this->resource->review->ulid,
                'status' => $this->resource->review->status->value,
                'product' => $this->resource->review->product?->name,
            ]),
        ];
    }
}
