<?php

namespace App\Enums;

enum ReviewReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /**
     * Only open reports require moderator attention.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
