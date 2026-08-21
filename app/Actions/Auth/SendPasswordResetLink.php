<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLink
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Dispatch a single-use, expiring reset link when the account exists.
     *
     * The response is intentionally uniform to prevent account enumeration.
     */
    public function __invoke(string $email): void
    {
        $status = Password::sendResetLink(['email' => strtolower(trim($email))]);

        if ($status === Password::RESET_LINK_SENT) {
            ($this->recordSecurityEvent)(
                User::where('email', strtolower(trim($email)))->first(),
                'password_reset_requested',
                SecuritySeverity::Info,
            );
        }
    }
}
