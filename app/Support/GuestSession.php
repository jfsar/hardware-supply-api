<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The guest engagement identity: the 64-char SHA-256 hash of the cart cookie
 * that ResolveCartToken stamps on every request (FR-DISC-001).
 */
class GuestSession
{
    /**
     * The session hash for a guest request, or null when absent.
     */
    public static function hash(Request $request): ?string
    {
        $hash = $request->attributes->get('cart_token_hash');

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
