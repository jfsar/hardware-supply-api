<?php

namespace App\Actions\Cart;

use App\Exceptions\Cart\VariantNotPurchasableException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Inventory\AvailableStock;

/**
 * Replaces a line's quantity after re-checking availability, capping at
 * current stock (FR-CART-009 safe degradation).
 */
class UpdateCartItem
{
    public function __construct(protected AvailableStock $availableStock) {}

    /**
     * @throws VariantNotPurchasableException when the variant is no longer sellable
     * @throws InsufficientStockException when availability has dropped to zero
     */
    public function __invoke(CartItem $item, float $quantity): CartItem
    {
        $variant = $item->variant;

        if ($variant === null || ! $this->purchasable($variant)) {
            throw VariantNotPurchasableException::forSku($variant->sku ?? 'unknown');
        }

        $available = max(0.0, ($this->availableStock)($variant));

        if ($available <= 0.0) {
            throw InsufficientStockException::forSkus([$variant->sku => $quantity]);
        }

        $item->forceFill(['quantity' => min($quantity, $available)])->save();

        return $item->refresh();
    }

    protected function purchasable(ProductVariant $variant): bool
    {
        return $variant->isPurchasable()
            && ! $variant->trashed()
            && $variant->product !== null
            && ! $variant->product->trashed();
    }
}
