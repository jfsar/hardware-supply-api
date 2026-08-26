<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Cart $resource
 */
class CartResource extends JsonResource
{
    /**
     * The cart payload; preview totals ride alongside under `totals`
     * and are always flagged non-authoritative (FR-CART-005).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'currency_code' => $this->resource->currency_code,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'coupons' => $this->whenLoaded('couponRows', fn (): array => $this->resource->couponRows
                ->filter(fn ($row): bool => $row->coupon !== null)
                ->map(fn ($row): string => (string) $row->coupon->code)
                ->values()
                ->all()),
        ];
    }
}
