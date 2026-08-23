<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Abort unless the authenticated user holds at least one required permission.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        abort_unless(
            collect($permissions)->some(
                fn (string $permission): bool => $user->hasPermissionTo($permission),
            ),
            403,
            __('You do not have permission to perform this action.'),
        );

        return $next($request);
    }
}
