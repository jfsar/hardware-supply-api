<?php

namespace App\Actions\Privacy;

use App\Enums\SecuritySeverity;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Irreversibly strip PII from an account queued for deletion after the
 * grace window (NFR-PRIV-001/002). Block identifiers give way to a
 * non-reversible placeholder, credentials are voided, and the row is
 * soft-deleted so downstream joins keep financial integrity without the
 * ability to reverse the anonymization.
 */
class AnonymizeAccount
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    public function __invoke(User $user): User
    {
        if ($user->status !== UserStatus::Deleted) {
            throw new UnprocessableEntityHttpException(__('Only accounts under deletion can be anonymized.'));
        }

        $user->forceFill([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'deleted-'.strtolower((string) $user->ulid).'@anonymized.invalid',
            'phone' => null,
            'password' => '!'.Str::random(48),
            'email_verified_at' => null,
            'two_factor_enabled' => false,
            'last_login_at' => null,
            'deleted_at' => now(),
        ])->save();

        $user->tokens()->delete();

        $user->sessions()->update(['revoked_at' => now()]);

        $user->twoFactorCredential()->delete();

        ($this->recordSecurityEvent)($user, 'account_anonymized', SecuritySeverity::Warning);

        return $user->fresh();
    }
}
