<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use Illuminate\Support\Carbon;

/**
 * Resolves the active cart for the caller: authenticated users get their
 * own cart, guests their token-hash cart. Creates one when allowed.
 */
class ResolveCart
{
    /**
     * @param  int|null  $userId  authenticated caller id, null for guests
     * @param  string|null  $tokenHash  SHA-256 cart token for guests
     * @return Cart|null null when nothing exists and creation is disabled
     */
    public function __invoke(?int $userId, ?string $tokenHash, bool $create = true): ?Cart
    {
        $now = Carbon::now();

        $query = Cart::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));

        $existing = $userId !== null
            ? $query->clone()->where('user_id', $userId)->first()
            : ($tokenHash !== null
                ? $query->clone()->whereNull('user_id')->where('session_token_hash', $tokenHash)->first()
                : null);

        if ($existing !== null || ! $create) {
            return $existing;
        }

        return Cart::query()->create([
            'user_id' => $userId,
            'session_token_hash' => $userId === null ? $tokenHash : null,
            'status' => 'active',
            'currency_code' => (string) config('commerce.currency', 'PHP'),
            'expires_at' => $userId === null
                ? $now->copy()->addDays((int) config('commerce.cart.ttl_days', 30))
                : null,
        ]);
    }
}
