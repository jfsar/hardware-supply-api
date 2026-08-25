<?php

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;

/**
 * Builds OpenAPI responses shaped exactly like ApiExceptionRenderer output:
 * {error: {code, message, details}, request_id}.
 */
class ErrorEnvelope
{
    /**
     * Create an error response in the API's error envelope.
     */
    public static function response(int $status, string $description, string $errorCode, ?OpenApiTypes\Type $details = null): Response
    {
        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType(self::body($errorCode, $details)));
    }

    /**
     * The full envelope body for the given stable error code.
     */
    private static function body(string $errorCode, ?OpenApiTypes\Type $details): OpenApiTypes\ObjectType
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('error', self::error($errorCode, $details))
            ->addProperty('request_id', (new OpenApiTypes\StringType)
                ->setDescription('Correlation id assigned by the AppendRequestId middleware (ULID).'))
            ->setRequired(['error', 'request_id']);
    }

    /**
     * The error object carrying the stable code, message, and optional details.
     */
    private static function error(string $errorCode, ?OpenApiTypes\Type $details): OpenApiTypes\ObjectType
    {
        return (new OpenApiTypes\ObjectType)
            ->addProperty('code', (new OpenApiTypes\StringType)
                ->setDescription('Stable machine-readable error code.')
                ->examples([$errorCode]))
            ->addProperty('message', (new OpenApiTypes\StringType)
                ->setDescription('Human-readable error summary.'))
            ->addProperty('details', $details ?? new OpenApiTypes\ObjectType)
            ->setRequired(['code', 'message', 'details']);
    }
}
