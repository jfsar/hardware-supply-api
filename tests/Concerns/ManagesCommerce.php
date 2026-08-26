<?php

namespace Tests\Concerns;

use App\Models\Inventory;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;

/**
 * Shared commerce fixtures: priced, stocked variants and address
 * payloads for cart/checkout/order tests (Phase 4).
 */
trait ManagesCommerce
{
    use ManagesInventory;

    /**
     * A purchasable variant with a default-list price and on-hand stock.
     */
    protected function pricedVariant(int $priceMinor, float $stock = 10.0): ProductVariant
    {
        $this->primaryWarehouse();

        $variant = ProductVariant::factory()->create();

        PriceListItem::factory()->forPricing($this->defaultPriceList(), $variant, $priceMinor)->create();

        Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->firstOrFail()
            ->forceFill(['quantity_on_hand' => $stock])
            ->save();

        return $variant;
    }

    /**
     * The singleton default price list tests resolve against.
     */
    protected function defaultPriceList(): PriceList
    {
        return PriceList::query()->where('is_default', true)->first()
            ?? PriceList::factory()->default()->create();
    }

    /**
     * A valid shipping address payload for checkout.
     *
     * @return array<string, mixed>
     */
    protected function shippingAddress(): array
    {
        return [
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '+639171234567',
            'address_line1' => '123 Rizal Street',
            'address_line2' => 'Barangay San Isidro',
        ];
    }

    /**
     * Extract the Cart-Token header issued by the API.
     */
    protected function cartTokenFromResponse($response): string
    {
        return (string) $response->headers->get('Cart-Token');
    }
}
