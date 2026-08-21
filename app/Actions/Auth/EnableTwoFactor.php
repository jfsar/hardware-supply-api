<?php

namespace App\Actions\Auth;

use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Validation\ValidationException;

class EnableTwoFactor
{
    public function __construct(
        protected Totp $totp,
    ) {}

    /**
     * Begin two-factor enrollment by storing an encrypted pending secret.
     *
     * Re-enrollment replaces any previous unconfirmed secret.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    public function __invoke(User $user): array
    {
        $credential = TwoFactorCredential::query()->firstOrNew(['user_id' => $user->id]);

        if ($credential->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => __('Two-factor authentication is already enabled.'),
            ]);
        }

        $secret = $this->totp->generateSecret();

        $credential->setSecret($secret);
        $credential->confirmed_at = null;
        $credential->recovery_codes_encrypted = null;
        $credential->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->otpauthUri(
                (string) config('app.name'),
                $user->email,
                $secret,
            ),
        ];
    }
}
