<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreProductImage
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Persist an uploaded image under products/{product-ulid}/ on the media
     * disk and register the gallery row. Server-side naming only (SRS §45).
     *
     * @param  array<string, mixed>  $options  sort_order/is_primary/product_variant_id
     */
    public function __invoke(User $actor, Product $product, UploadedFile $file, array $options = []): ProductImage
    {
        $disk = (string) config('catalog.media_disk', 'public');
        $filename = ((string) Str::ulid()).'.'.strtolower((string) $file->getClientOriginalExtension() ?: 'jpg');
        $directory = 'products/'.$product->ulid;

        $image = DB::transaction(function () use ($file, $disk, $directory, $filename, $product, $options): ProductImage {
            Storage::disk($disk)->putFileAs($directory, $file, $filename);

            [$width, $height] = $this->dimensions($file);

            // Enforce the single-primary rule inside the transaction.
            $makePrimary = ($options['is_primary'] ?? false) === true
                || ! $product->images()->where('is_primary', true)->exists();

            if ($makePrimary) {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->update(['is_primary' => false]);
            }

            return $product->images()->create([
                'product_variant_id' => isset($options['product_variant_id']) ? (int) $options['product_variant_id'] : null,
                'storage_disk' => $disk,
                'path' => $directory.'/'.$filename,
                'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                'width' => $width,
                'height' => $height,
                'sort_order' => (int) ($options['sort_order'] ?? ($product->images()->count())),
                'is_primary' => $makePrimary,
            ]);
        });

        $this->recordAuditLog->model($actor, 'product_image.uploaded', $image);

        return $image;
    }

    /**
     * Pixel dimensions when the upload is a readable raster image.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());

        return is_array($info) ? [(int) $info[0], (int) $info[1]] : [null, null];
    }
}
