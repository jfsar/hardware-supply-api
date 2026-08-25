<?php

namespace App\Http\Resources\Catalog;

use App\Models\ProductDocument;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductDocument $resource
 */
class ProductDocumentResource extends JsonResource
{
    /**
     * Transform the document into an array with a resolvable URL.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => MediaUrl::for($this->storage_disk, $this->path),
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'sort_order' => $this->sort_order,
        ];
    }
}
