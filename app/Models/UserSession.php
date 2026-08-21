<?php

namespace App\Models;

use Database\Factories\UserSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'device_name', 'user_agent', 'ip_address', 'last_used_at', 'expires_at', 'revoked_at'])]
class UserSession extends Model
{
    /** @use HasFactory<UserSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Hash a plain bearer token the same way session rows are keyed.
     *
     * The hash covers the raw token segment after the "id|" separator so it
     * matches both Sanctum's stored token digest and presented credentials.
     */
    public static function hashPlainToken(string $plainTextToken): string
    {
        $raw = str_contains($plainTextToken, '|')
            ? substr($plainTextToken, strpos($plainTextToken, '|') + 1)
            : $plainTextToken;

        return hash('sha256', $raw);
    }

    /**
     * Restrict to sessions that are neither revoked nor expired.
     */
    public function scopeActive($query): mixed
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * The user that owns this session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
