<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ResetUserPassword
{
    public function __construct(
        protected IssueUserSession $issueUserSession,
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Consume a single-use reset token and set the new password.
     *
     * Every active session is revoked so stolen tokens cannot survive a reset.
     */
    public function __invoke(string $email, string $password, string $token): void
    {
        $status = Password::reset(
            [
                'email' => strtolower(trim($email)),
                'password' => $password,
                'token' => $token,
            ],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill(['password' => Hash::make($password)])->save();

                    ($this->issueUserSession)->revokeAllFor($user);
                });

                ($this->recordSecurityEvent)($user, 'password_changed', SecuritySeverity::Info);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }
    }
}
