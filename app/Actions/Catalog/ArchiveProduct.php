<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class ArchiveProduct
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Archive (soft-delete) a product, removing it from every listing while
     * keeping it resolvable by admins (FR-CAT-010).
     */
    public function __invoke(User $actor, Product $product): Product
    {
        DB::transaction(function () use (&$product): void {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $product->status = ProductStatus::Archived;
            $product->save();
            $product->delete();
        });

        $this->recordAuditLog->model($actor, 'product.archived', $product->refresh());

        return $product->refresh();
    }
}
