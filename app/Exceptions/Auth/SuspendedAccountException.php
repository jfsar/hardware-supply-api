<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class SuspendedAccountException extends RuntimeException
{
    /**
     * Create a suspension rejection for the given email.
     */
    public static function forEmail(string $email): self
    {
        return new self(__('This account has been suspended.'));
    }
}
