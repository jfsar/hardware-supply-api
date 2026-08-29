<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest-or-auth endpoints launch without the auth middleware, so the web
 * guard never fills Request::user(). Whenever a bearer token is present,
 * resolve the Sanctum user and install it on the request so engagement
 * endpoints personalise for signed-in customers while guests stay guests.
 */
class ResolveOptionalAuthenticatedUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $user = Auth::guard('sanctum')->user();

            if ($user !== null) {
                $request->setUserResolver(fn (): ?\App\Models\User => $user);
            }
        }

        return $next($request);
    }
}
