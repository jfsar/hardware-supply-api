<?php

namespace App\Http\Resources\Admin;

use App\Models\OrderAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One append-only manual price adjustment row (SRS §69).
 *
 * @property OrderAdjustment $resource
 */
class AdminOrderAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'type' => $this->resource->type,
            'label' => $this->resource->label,
            'amount_minor' => (int) $this->resource->amount_minor,
            'currency_code' => $this->resource->currency_code,
            'reason' => $this->resource->reason,
            'created_by_user_id' => $this->resource->created_by_user_id,
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
