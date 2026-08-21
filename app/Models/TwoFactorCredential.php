<?php

namespace App\Models;

use Database\Factories\TwoFactorCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'secret_encrypted', 'recovery_codes_encrypted', 'confirmed_at'])]
class TwoFactorCredential extends Model
{
    /** @use HasFactory<TwoFactorCredentialFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The decrypted TOTP shared secret.
     */
    public function secret(): string
    {
        return decrypt($this->secret_encrypted);
    }

    /**
     * Store the TOTP shared secret encrypted.
     */
    public function setSecret(string $secret): void
    {
        $this->secret_encrypted = encrypt($secret);
    }

    /**
     * The decrypted recovery codes; empty when none are stored.
     *
     * @return list<string>
     */
    public function recoveryCodes(): array
    {
        if ($this->recovery_codes_encrypted === null) {
            return [];
        }

        return json_decode(decrypt($this->recovery_codes_encrypted), true) ?: [];
    }

    /**
     * Replace the stored recovery codes with an encrypted copy.
     *
     * @param  list<string>  $codes
     */
    public function setRecoveryCodes(array $codes): void
    {
        $this->recovery_codes_encrypted = encrypt(json_encode($codes));
    }

    /**
     * Consume a single recovery code, keeping the remainder.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->recoveryCodes();

        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $this->setRecoveryCodes(array_values($codes));

        return true;
    }

    /**
     * The user that owns this credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
