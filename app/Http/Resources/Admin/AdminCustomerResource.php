<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin customer profile (FR-ADMIN-001). Deliberately excludes password
 * hashes, 2FA secrets, and recovery codes (NFR-SEC-010); the loaded
 * saved delivery address and lifetime order summary render when present.
 *
 * @property User $resource
 */
class AdminCustomerResource extends JsonResource
{
    /**
     * @var array{count: int, total_orders_minor: int, last_order_at: mixed}|null
     */
    protected ?array $orderSummary = null;

    /**
     * Attach the aggregate order counters computed separately.
     *
     * @param  array{count: int, total_orders_minor: int, last_order_at: mixed}  $summary
     */
    public function withOrderSummary(array $summary): static
    {
        $this->orderSummary = $summary;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->resource->ulid,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'name' => trim(($this->resource->first_name ?? '').' '.($this->resource->last_name ?? '')),
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'status' => $this->resource->status?->value,
            'two_factor_enabled' => $this->resource->two_factor_enabled,
            'email_verified_at' => $this->resource->email_verified_at?->toISOString(),
            'address' => $this->whenLoaded('address', fn (): ?array => $this->resource->address?->only([
                'address_line1',
                'address_line2',
                'recipient_name',
                'recipient_phone',
                'notes',
            ])),
            'order_summary' => $this->when($this->orderSummary !== null, $this->orderSummary),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
