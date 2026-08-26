<?php

namespace App\Http\Middleware;

use App\Exceptions\Http\IdempotencyConflictException;
use App\Exceptions\Http\IdempotencyKeyRequiredException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency infrastructure for financially consequential endpoints
 * (SRS §10, NFR-SEC-008 / Phase 4 Task 7).
 *
 * - Requires an Idempotency-Key header (422 IDEMPOTENCY_KEY_REQUIRED).
 * - Scopes uniqueness to (user_id | anonymous cart-token hash) + endpoint.
 * - Replays a stored response verbatim when the SHA-256 request hash
 *   matches and the record is within TTL; any payload drift is a 409
 *   IDEMPOTENCY_CONFLICT.
 * - Business actions may persist their own response inside their
 *   transaction (checkout step 19) by invoking the recorder closure this
 *   middleware attaches under the request attribute below; otherwise the
 *   middleware stores the response after it completes.
 */
class EnsureIdempotency
{
    public const RECORDER_ATTRIBUTE = 'idempotency_recorder';

    public const RECORDED_ATTRIBUTE = 'idempotency_recorded';

    /**
     * Handle an incoming request. The $endpoint parameter names the
     * logical operation (e.g. checkout.place, orders.cancel).
     */
    public function handle(Request $request, Closure $next, string $endpoint): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '' || strlen($key) > 255) {
            throw IdempotencyKeyRequiredException::missing();
        }

        $userId = $request->user()?->getAuthIdentifier();
        $anonymousScope = null;

        if ($userId === null) {
            /** @var string|null $tokenHash */
            $tokenHash = $request->attributes->get(ResolveCartToken::HASH_ATTRIBUTE);
            $anonymousScope = (string) ($tokenHash ?? 'no-scope');
        }

        $storedEndpoint = IdempotencyKey::scopedEndpoint($endpoint, $userId !== null ? (int) $userId : null, $anonymousScope);
        $requestHash = hash('sha256', (string) $request->getContent());

        $existing = IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('endpoint', $storedEndpoint)
            ->where('key', $key)
            ->first();

        if ($existing !== null && $existing->expires_at->isFuture()) {
            if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                throw IdempotencyConflictException::payloadMismatch();
            }

            return response(
                (string) $existing->response_body,
                (int) $existing->response_status,
            )->header('Content-Type', 'application/json')
                ->header('X-Idempotency-Replay', 'true');
        }

        // Expired records free the key for reuse.
        $existing?->delete();

        $recorded = false;
        $request->attributes->set(self::RECORDED_ATTRIBUTE, false);
        $request->attributes->set(self::RECORDER_ATTRIBUTE, function (int $status, array|string $body) use ($userId, $storedEndpoint, $key, $requestHash, &$recorded): void {
            IdempotencyKey::query()->create([
                'user_id' => $userId !== null ? (int) $userId : null,
                'endpoint' => $storedEndpoint,
                'key' => $key,
                'request_hash' => $requestHash,
                'response_status' => $status,
                'response_body' => is_string($body) ? $body : json_encode($body),
                'expires_at' => now()->addMinutes((int) config('commerce.idempotency.ttl_minutes', 1440)),
            ]);

            $recorded = true;
        });

        /** @var Response $response */
        $response = $next($request);

        // Fallback persistence for wrapped endpoints whose actions did
        // not record inside their own transaction.
        if (! $recorded && $response->isSuccessful()) {
            try {
                IdempotencyKey::query()->create([
                    'user_id' => $userId !== null ? (int) $userId : null,
                    'endpoint' => $storedEndpoint,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'expires_at' => now()->addMinutes((int) config('commerce.idempotency.ttl_minutes', 1440)),
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent duplicate won the race; its response stands.
            }
        }

        return $response;
    }
}
