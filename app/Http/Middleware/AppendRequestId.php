<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AppendRequestId
{
    /**
     * Assign a correlation id to the request and expose it on JSON responses.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        if (str_contains($response->headers->get('Content-Type', ''), 'json')) {
            $payload = json_decode((string) $response->getContent(), true);

            if (is_array($payload) && ! array_key_exists('request_id', $payload)) {
                $payload['request_id'] = $requestId;
                $response->setContent(json_encode($payload));
            }
        }

        return $response;
    }
}
