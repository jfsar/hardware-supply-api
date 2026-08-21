<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\RecordSecurityEvent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class VerifyUserEmail
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Mark the account verified when the signed link hash matches.
     *
     * Returns true when this call performed the verification.
     */
    public function __invoke(User $user, string $hash): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        });

        ($this->recordSecurityEvent)($user, 'email_verified');

        return true;
    }
}
