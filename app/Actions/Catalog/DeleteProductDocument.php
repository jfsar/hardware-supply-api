<?php

namespace App\Actions\Catalog;

use App\Models\ProductDocument;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteProductDocument
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Remove the document row, then the underlying file.
     */
    public function __invoke(User $actor, ProductDocument $document): void
    {
        [$disk, $path] = DB::transaction(function () use ($document): array {
            $disk = $document->storage_disk;
            $path = $document->path;

            $document->delete();

            return [$disk, $path];
        });

        if (! Storage::disk($disk)->delete($path)) {
            report(new \RuntimeException("Failed deleting document file {$disk}:{$path}"));
        }

        ($this->recordAuditLog)($actor, 'product_document.deleted', 'ProductDocument', (int) $document->getKey(), [
            'disk' => $disk,
            'path' => $path,
        ]);
    }
}
