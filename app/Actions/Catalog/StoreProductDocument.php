<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreProductDocument
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Persist an uploaded manual under products/{product-ulid}/ and register
     * the document row.
     *
     * @param  array<string, mixed>  $options  title/sort_order/product_variant_id
     */
    public function __invoke(User $actor, Product $product, UploadedFile $file, array $options = []): ProductDocument
    {
        $disk = (string) config('catalog.media_disk', 'public');
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'pdf'));
        $filename = ((string) Str::ulid()).'.'.strtolower($extension);
        $directory = 'products/'.$product->ulid;

        /** @var ProductVariant|null $variant */
        $variant = isset($options['product_variant_id'])
            ? ProductVariant::query()->where('product_id', $product->id)->find((int) $options['product_variant_id'])
            : null;

        abort_if(
            isset($options['product_variant_id']) && $variant === null,
            422,
            __('The selected variant does not belong to this product.'),
        );

        $document = DB::transaction(function () use ($file, $disk, $directory, $filename, $product, $variant, $options): ProductDocument {
            Storage::disk($disk)->putFileAs($directory, $file, $filename);

            return $product->documents()->create([
                'product_variant_id' => $variant?->id,
                'title' => (string) ($options['title'] ?? 'Manual'),
                'storage_disk' => $disk,
                'path' => $directory.'/'.$filename,
                'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                'file_size_bytes' => (int) ($file->getSize() ?: 0),
                'sort_order' => (int) ($options['sort_order'] ?? 0),
            ]);
        });

        $this->recordAuditLog->model($actor, 'product_document.uploaded', $document);

        return $document;
    }
}
