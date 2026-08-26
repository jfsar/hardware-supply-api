<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Payment $resource
 */
class PaymentResource extends JsonResource
{
    /**
     * Minimal Phase 4 payment view; gateway detail arrives in Phase 5.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'provider' => $this->resource->provider,
            'method' => $this->resource->payment_method,
            'status' => $this->resource->status->value,
            'amount_minor' => (int) $this->resource->amount_minor,
            'currency_code' => $this->resource->currency_code,
            'paid_at' => optional($this->resource->paid_at)->toISOString(),
        ];
    }
}
