<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    /**
     * Only approved reviews are rendered through the public API (FR-REV-004).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
