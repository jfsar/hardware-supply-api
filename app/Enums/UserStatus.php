<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    /**
     * Whether the account is suspended and must not authenticate.
     */
    public function isSuspended(): bool
    {
        return $this === self::Suspended;
    }
}
