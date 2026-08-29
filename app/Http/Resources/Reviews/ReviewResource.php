<?php

namespace App\Http\Resources\Reviews;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Review $resource
 */
class ReviewResource extends JsonResource
{
    /**
     * Customer-facing review payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'verified_purchase' => $this->verified_purchase,
            'author' => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'name' => trim(($this->author->first_name ?? '').' '.($this->author->last_name ?? '')),
            ]),
            'helpful_count' => $this->whenCounted(
                'helpfulVotes',
                fn (): int => (int) $this->helpful_votes_count,
            ),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
