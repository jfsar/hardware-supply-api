<?php

namespace App\Http\Resources\Admin;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin moderation view of a customer review.
 *
 * @property Review $resource
 */
class AdminReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'rating' => $this->resource->rating,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'status' => $this->resource->status->value,
            'verified_purchase' => $this->resource->verified_purchase,
            'published_at' => $this->resource->published_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'product' => $this->whenLoaded('product', fn (): ?array => $this->resource->product === null ? null : [
                'id' => $this->resource->product->ulid,
                'name' => $this->resource->product->name,
                'slug' => $this->resource->product->slug,
            ]),
            'author' => $this->whenLoaded('author', fn (): ?array => $this->resource->author === null ? null : [
                'id' => $this->resource->author->ulid,
                'name' => trim(($this->resource->author->first_name ?? '').' '.($this->resource->author->last_name ?? '')),
                'email' => $this->resource->author->email,
            ]),
            'reports_count' => $this->whenCounted(
                'reports',
                fn (): int => (int) $this->resource->reports_count,
            ),
        ];
    }
}
