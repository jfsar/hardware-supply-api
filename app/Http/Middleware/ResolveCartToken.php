<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest cart identity (Phase 4 Task 2): reads or issues an opaque
 * cart_token via header/cookie and exposes only its SHA-256 hash to the
 * application — carts.session_token_hash never sees a raw token.
 *
 * Runs for the whole api group so login requests can merge guest carts.
 * Downstream consumers read request attributes:
 *   cart_token      raw token issued/accepted for this request
 *   cart_token_hash SHA-256 of that token
 */
class ResolveCartToken
{
    public const HEADER = 'Cart-Token';

    public const COOKIE = 'cart_token';

    public const ATTRIBUTE = 'cart_token';

    public const HASH_ATTRIBUTE = 'cart_token_hash';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(self::HEADER) ?? $request->cookie(self::COOKIE);
        $token = is_string($incoming) && preg_match('/^[a-f0-9]{64}$/', $incoming) === 1
            ? $incoming
            : null;

        $issued = false;

        if ($token === null) {
            $token = hash('sha256', Str::random(40));
            $issued = true;
        }

        $request->attributes->set(self::ATTRIBUTE, $token);
        $request->attributes->set(self::HASH_ATTRIBUTE, self::hash($token));

        /** @var Response $response */
        $response = $next($request);

        // Fresh tokens travel back on every response so clients persist
        // them; existing tokens only echo when they arrived by header.
        if ($issued || ! $this->hasCookie($request)) {
            $ttlMinutes = max(1, (int) config('commerce.cart.ttl_days', 30) * 24 * 60);

            $response->headers->set(self::HEADER, $token);

            $response->headers->setCookie(cookie(
                self::COOKIE,
                $token,
                $ttlMinutes,
                '/',
                null,
                config('session.secure', false),
                true,
                false,
                'Lax',
            ));
        }

        return $response;
    }

    /**
     * Whether the caller presented the token as a cookie.
     */
    private function hasCookie(Request $request): bool
    {
        return is_string($request->cookie(self::COOKIE))
            && preg_match('/^[a-f0-9]{64}$/', (string) $request->cookie(self::COOKIE)) === 1;
    }

    /**
     * The stored representation of a token.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
