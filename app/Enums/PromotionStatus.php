<?php

namespace App\Enums;

/**
 * Promotion lifecycle stored in promotions.status (default 'active').
 */
enum PromotionStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Paused = 'paused';
    case Ended = 'ended';
    case Archived = 'archived';

    /**
     * Whether the promotion may currently apply.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
