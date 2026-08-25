<?php

namespace App\OpenApi\Extensions;

use App\OpenApi\ErrorEnvelope;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents a 429 envelope response on every route guarded by a named
 * throttle limiter, matching the routes.md convention.
 */
final class ThrottledResponsesExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $isThrottled = fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:');

        if (! collect($routeInfo->route->gatherMiddleware())->contains($isThrottled)) {
            return;
        }

        if (collect($operation->responses)->contains(
            fn ($response) => $response instanceof Response && (int) $response->code === 429,
        )) {
            return;
        }

        $operation->addResponse(
            ErrorEnvelope::response(429, 'Rate limit exceeded', 'TOO_MANY_REQUESTS')
                ->addHeader('Retry-After', (new Header)
                    ->setDescription('Seconds to wait before retrying.')
                    ->setRequired(true)
                    ->setSchema(Schema::fromType((new OpenApiTypes\IntegerType)
                        ->setDescription('Seconds until the limiter resets.')))),
        );
    }
}
