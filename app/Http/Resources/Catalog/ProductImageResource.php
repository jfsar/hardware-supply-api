<?php

namespace App\Http\Resources\Catalog;

use App\Models\ProductImage;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductImage $resource
 */
class ProductImageResource extends JsonResource
{
    /**
     * Transform the image into an array with a resolvable URL.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => MediaUrl::for($this->storage_disk, $this->path),
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
