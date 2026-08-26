<?php

namespace App\Actions\Cart;

use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Inventory\AvailableStock;

/**
 * Adds a purchasable variant to a cart, merging duplicate rows via the
 * unique (cart_id, product_variant_id) index and capping the resulting
 * quantity at currently available stock (Phase 4 Task 2).
 */
class AddToCart
{
    public function __construct(protected AvailableStock $availableStock) {}

    /**
     * @throws VariantNotPurchasableException when the variant is not sellable
     * @throws InsufficientStockException when no stock is available
     */
    public function __invoke(Cart $cart, ProductVariant $variant, float $quantity): CartItem
    {
        $this->assertPurchasable($variant);

        $available = max(0.0, ($this->availableStock)($variant));

        if ($available <= 0.0) {
            throw InsufficientStockException::forSkus([$variant->sku => $quantity]);
        }

        $existing = CartItem::query()
            ->where('cart_id', $cart->getKey())
            ->where('product_variant_id', $variant->getKey())
            ->first();

        $desired = ($existing?->quantity ?? 0.0) + $quantity;

        return CartItem::query()->updateOrCreate(
            ['cart_id' => $cart->getKey(), 'product_variant_id' => $variant->getKey()],
            ['quantity' => min($desired, $available)],
        );
    }

    /**
     * Variants must be active and belong to a live product (FR-CART-009).
     */
    protected function assertPurchasable(ProductVariant $variant): void
    {
        if (! $variant->isPurchasable()
            || $variant->trashed()
            || $variant->product === null
            || $variant->product->trashed()
        ) {
            throw VariantNotPurchasableException::forSku($variant->sku);
        }
    }
}
