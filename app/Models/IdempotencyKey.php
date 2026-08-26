<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stored idempotent request/response pairs for financially consequential
 * endpoints (SRS §10). Anonymous requests scope under a cart-token hash
 * encoded into the endpoint column because MySQL unique indexes treat
 * NULL user_id values as distinct.
 */
#[Fillable(['user_id', 'key', 'endpoint', 'request_hash', 'response_status', 'response_body', 'expires_at'])]
#[Hidden(['request_hash', 'response_body'])]
class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    /**
     * Endpoint column prefix used to scope anonymous keys deterministically.
     */
    public const ANONYMOUS_SCOPE_SEPARATOR = '|anon:';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The authenticated owner, null when scoped anonymously.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Build the stored endpoint value for the given caller scope.
     */
    public static function scopedEndpoint(string $endpoint, ?int $userId, ?string $anonymousScope): string
    {
        return $userId !== null
            ? $endpoint
            : $endpoint.self::ANONYMOUS_SCOPE_SEPARATOR.$anonymousScope;
    }
}
