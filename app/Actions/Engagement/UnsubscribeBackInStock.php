<?php

namespace App\Actions\Engagement;

use App\Enums\AlertSubscriptionStatus;
use App\Models\BackInStockSubscription;
use App\Models\ProductVariant;

class UnsubscribeBackInStock
{
    /**
     * Soft-deactivate the stock alert; the email history is preserved and a
     * later subscribe call reactivates the same row.
     */
    public function __invoke(string $email, ProductVariant $variant): bool
    {
        $subscription = BackInStockSubscription::query()
            ->where('email', $email)
            ->where('product_variant_id', $variant->getKey())
            ->where('status', AlertSubscriptionStatus::Active->value)
            ->first();

        if ($subscription === null) {
            return false;
        }

        $subscription->status = AlertSubscriptionStatus::Inactive;
        $subscription->save();

        return true;
    }
}
