<?php

namespace App\Enums;

enum AlertSubscriptionStatus: string
{
    case Active = 'active';
    case Notified = 'notified';
    case Inactive = 'inactive';

    /**
     * Only active subscriptions are considered by the notify jobs.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
