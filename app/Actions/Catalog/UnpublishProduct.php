<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class UnpublishProduct
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Withdraw a product from the public catalog while keeping its history.
     */
    public function __invoke(User $actor, Product $product): Product
    {
        DB::transaction(function () use (&$product): void {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $product->status = ProductStatus::Inactive;
            $product->save();
        });

        $this->recordAuditLog->model($actor, 'product.unpublished', $product->refresh());

        return $product->refresh();
    }
}
