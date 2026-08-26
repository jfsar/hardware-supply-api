<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Gates queued notifications on per-customer preferences (Phase 4
 * Task 10; management UI lands Phase 7). A missing preference row means
 * the customer has never opted out — every category stays enabled.
 */
class NotificationPreferenceGate
{
    /**
     * Whether the user accepts notifications of a given category.
     */
    public function allows(User $user, string $category): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($preference === null) {
            return true;
        }

        return match ($category) {
            'order_updates' => (bool) $preference->order_updates_enabled,
            'payment_updates' => (bool) $preference->payment_updates_enabled,
            'promotions' => (bool) $preference->promotions_enabled,
            'back_in_stock' => (bool) $preference->back_in_stock_enabled,
            'price_drop' => (bool) $preference->price_drop_enabled,
            default => true,
        };
    }
}
