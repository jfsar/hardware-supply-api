<?php

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Refund $resource
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'payment_ulid' => (string) $this->resource->payment->ulid,
            'status' => $this->resource->status->value,
            'amount_minor' => (int) $this->resource->amount_minor,
            'currency_code' => $this->resource->currency_code,
            'reason' => $this->resource->reason,
            'requested_at' => optional($this->resource->requested_at)->toISOString(),
            'processed_at' => optional($this->resource->processed_at)->toISOString(),
        ];
    }
}
