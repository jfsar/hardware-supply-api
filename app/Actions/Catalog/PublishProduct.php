<?php

namespace App\Actions\Catalog;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Exceptions\Catalog\ProductNotPublishableException;
use App\Models\Product;
use App\Models\User;
use App\Services\RecordAuditLog;
use Illuminate\Support\Facades\DB;

class PublishProduct
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Flip a product to Active, rejecting products without an active variant.
     *
     * @throws ProductNotPublishableException when the product has no active variant
     */
    public function __invoke(User $actor, Product $product): Product
    {
        DB::transaction(function () use (&$product): void {
            /** @var Product $product */
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $hasActiveVariant = $product->variants()
                ->where('status', VariantStatus::Active->value)
                ->whereNull('deleted_at')
                ->exists();

            if (! $hasActiveVariant) {
                throw new ProductNotPublishableException(
                    __('A product needs at least one active variant before it can be published.'),
                );
            }

            $product->status = ProductStatus::Active;
            $product->published_at ??= now();
            $product->save();
        });

        $this->recordAuditLog->model($actor, 'product.published', $product->refresh());

        return $product->refresh();
    }
}
