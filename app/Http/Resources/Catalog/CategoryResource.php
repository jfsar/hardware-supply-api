<?php

namespace App\Http\Resources\Catalog;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Category $resource
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the category into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
            ],
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn (): array => self::collection($this->children)->resolve(),
            ),
        ];
    }
}
