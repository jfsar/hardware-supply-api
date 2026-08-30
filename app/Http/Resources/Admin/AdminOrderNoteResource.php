<?php

namespace App\Http\Resources\Admin;

use App\Models\OrderNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff note attached to an order, including its author.
 *
 * @property OrderNote $resource
 */
class AdminOrderNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'note' => $this->resource->note,
            'is_customer_visible' => $this->resource->is_customer_visible,
            'author' => $this->whenLoaded('author', fn (): ?array => $this->resource->author === null ? null : [
                'id' => $this->resource->author->ulid,
                'name' => trim(($this->resource->author->first_name ?? '').' '.($this->resource->author->last_name ?? '')),
            ]),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
