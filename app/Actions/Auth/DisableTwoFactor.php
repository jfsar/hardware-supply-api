<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DisableTwoFactor
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Disable two-factor authentication after re-authentication.
     */
    public function __invoke(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('The provided password is incorrect.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->twoFactorCredential?->delete();

            $user->forceFill(['two_factor_enabled' => false])->save();
        });

        ($this->recordSecurityEvent)($user, 'two_factor_disabled', SecuritySeverity::Info);
    }
}
