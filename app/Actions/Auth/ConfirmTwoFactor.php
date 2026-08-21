<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use App\Services\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfirmTwoFactor
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        protected Totp $totp,
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Confirm enrollment with a valid TOTP code and activate 2FA.
     *
     * Recovery codes are returned once and never again in plaintext.
     *
     * @return list<string>
     */
    public function __invoke(User $user, string $code): array
    {
        $credential = $user->twoFactorCredential;

        if ($credential === null || $credential->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => __('Two-factor authentication is not pending confirmation.'),
            ]);
        }

        if (! $this->totp->verify($credential->secret(), trim($code))) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code was not valid.'),
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        DB::transaction(function () use ($user, $credential, $recoveryCodes): void {
            $credential->forceFill(['confirmed_at' => now()]);
            $credential->setRecoveryCodes($recoveryCodes);
            $credential->save();

            $user->forceFill(['two_factor_enabled' => true])->save();
        });

        ($this->recordSecurityEvent)($user, 'two_factor_enabled', SecuritySeverity::Info);

        return $recoveryCodes;
    }

    /**
     * Generate the one-time recovery codes.
     *
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
