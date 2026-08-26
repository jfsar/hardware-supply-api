<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Address snapshot taken at checkout (billing/shipping). Geography is
 * stored as FK ids plus literal lines so the order stays self-describing.
 */
#[Fillable([
    'order_id', 'address_type', 'country_id', 'region_id', 'province_id', 'city_id',
    'barangay_id', 'postal_code_id', 'address_line1', 'address_line2', 'recipient_name',
    'recipient_phone', 'latitude', 'longitude', 'notes',
])]
class OrderAddress extends Model
{
    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_BILLING = 'billing';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * The owning order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
