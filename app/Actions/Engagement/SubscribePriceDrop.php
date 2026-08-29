<?php

namespace App\Actions\Engagement;

use App\Enums\AlertSubscriptionStatus;
use App\Models\PriceDropSubscription;
use App\Models\ProductVariant;
use App\Models\User;

class SubscribePriceDrop
{
    /**
     * Upsert one active price alert per email + variant, replacing any
     * earlier target and resetting the notified marker.
     */
    public function __invoke(
        ?User $user,
        string $email,
        ProductVariant $variant,
        ?int $targetPriceMinor,
        string $currencyCode,
    ): PriceDropSubscription {
        return PriceDropSubscription::query()->updateOrCreate(
            ['email' => $email, 'product_variant_id' => $variant->getKey()],
            [
                'user_id' => $user?->getKey(),
                'target_price_minor' => $targetPriceMinor,
                'currency_code' => $currencyCode,
                'status' => AlertSubscriptionStatus::Active,
                'notified_at' => null,
            ],
        );
    }
}
