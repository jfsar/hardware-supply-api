<?php

namespace App\Actions\Auth;

use App\Models\User;

class ResendEmailVerification
{
    /**
     * Send a fresh verification link for an unverified account.
     */
    public function __invoke(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
