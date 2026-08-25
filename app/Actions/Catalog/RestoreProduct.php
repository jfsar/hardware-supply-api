<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class RestoreProduct
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Bring an archived product back as a draft so it re-enters review.
     */
    public function __invoke(User $actor, int $productId): Product
    {
        $product = DB::transaction(function () use ($productId): Product {
            /** @var Product $product */
            $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->firstOrFail();

            $product->restore();

            $product->status = ProductStatus::Draft;
            $product->published_at = null;
            $product->save();

            return $product;
        });

        $this->recordAuditLog->model($actor, 'product.restored', $product);

        return $product;
    }
}
