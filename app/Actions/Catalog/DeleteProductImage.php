<?php

namespace App\Actions\Catalog;

use App\Models\ProductImage;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteProductImage
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Remove the gallery row, then the underlying file. Storage failures are
     * reported but never block deletion of the record.
     */
    public function __invoke(User $actor, ProductImage $image): void
    {
        [$disk, $path] = DB::transaction(function () use ($image): array {
            $disk = $image->storage_disk;
            $path = $image->path;

            $image->delete();

            return [$disk, $path];
        });

        if (! Storage::disk($disk)->delete($path)) {
            report(new \RuntimeException("Failed deleting image file {$disk}:{$path}"));
        }

        ($this->recordAuditLog)($actor, 'product_image.deleted', 'ProductImage', (int) $image->getKey(), [
            'disk' => $disk,
            'path' => $path,
        ]);
    }
}
