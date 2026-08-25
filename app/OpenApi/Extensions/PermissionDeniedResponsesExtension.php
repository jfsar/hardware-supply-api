<?php

namespace App\OpenApi\Extensions;

use App\OpenApi\ErrorEnvelope;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents a 403 FORBIDDEN envelope response on every route guarded by the
 * permission middleware, since middleware failures are invisible to static
 * inference of controller actions.
 */
final class PermissionDeniedResponsesExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $isPermission = fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:');

        if (! collect($routeInfo->route->gatherMiddleware())->contains($isPermission)) {
            return;
        }

        if (collect($operation->responses)->contains(
            fn ($response) => $response instanceof Response && (int) $response->code === 403,
        )) {
            return;
        }

        $operation->addResponse(
            ErrorEnvelope::response(403, 'Missing required permission', 'FORBIDDEN'),
        );
    }
}
