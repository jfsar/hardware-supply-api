<?php

namespace App\Http\Resources;

use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CheckoutSession $resource
 */
class CheckoutResource extends JsonResource
{
    /**
     * Session lifecycle view (Phase 4 Task 8 artifacts). Totals ride on
     * the session row written at validation time.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'status' => $this->resource->status->value,
            'currency_code' => $this->resource->currency_code,
            'subtotal_minor' => (int) $this->resource->subtotal_minor,
            'discount_minor' => (int) $this->resource->discount_minor,
            'shipping_minor' => (int) $this->resource->shipping_minor,
            'tax_minor' => (int) $this->resource->tax_minor,
            'total_minor' => (int) $this->resource->total_minor,
            'expires_at' => optional($this->resource->expires_at)->toISOString(),
            'completed_at' => optional($this->resource->completed_at)->toISOString(),
            'order_ulid' => $this->when(
                $this->resource->relationLoaded('order') && $this->resource->order !== null,
                fn (): ?string => $this->resource->order?->ulid,
            ),
        ];
    }
}
