<?php

namespace App\Actions\Engagement;

use App\Enums\AlertSubscriptionStatus;
use App\Models\PriceDropSubscription;
use App\Models\ProductVariant;

class UnsubscribePriceDrop
{
    /**
     * Deactivate every price alert for an email + variant.
     */
    public function __invoke(string $email, ProductVariant $variant): void
    {
        PriceDropSubscription::query()
            ->where('email', $email)
            ->where('product_variant_id', $variant->getKey())
            ->where('status', AlertSubscriptionStatus::Active->value)
            ->update(['status' => AlertSubscriptionStatus::Inactive->value]);
    }
}
