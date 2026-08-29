<?php

namespace App\Actions\Engagement;

use App\Enums\AlertSubscriptionStatus;
use App\Models\BackInStockSubscription;
use App\Models\ProductVariant;
use App\Models\User;

class SubscribeBackInStock
{
    /**
     * Activate a stock alert for an email + variant. Re-activation revives
     * the single prior row (notified or inactive) so the unique
     * (email, variant, status) index never accumulates duplicates.
     */
    public function __invoke(?User $user, string $email, ProductVariant $variant): BackInStockSubscription
    {
        $subscription = BackInStockSubscription::query()
            ->where('email', $email)
            ->where('product_variant_id', $variant->getKey())
            ->first();

        if ($subscription !== null) {
            $subscription->status = AlertSubscriptionStatus::Active;
            $subscription->notified_at = null;
            $subscription->user_id ??= $user?->getKey();
            $subscription->save();

            return $subscription;
        }

        return BackInStockSubscription::query()->create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'product_variant_id' => $variant->getKey(),
            'status' => AlertSubscriptionStatus::Active,
        ]);
    }
}
