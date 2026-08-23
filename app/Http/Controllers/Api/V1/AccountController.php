<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RequestAccountDeletion;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAccountExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    /**
     * Queue an export of the customer's eligible personal data (FR-CUST-006).
     */
    public function requestExport(Request $request): JsonResponse
    {
        GenerateAccountExport::dispatch($request->user())->onQueue('notifications');

        return response()->json([
            'data' => [
                'message' => __('Your data export is being prepared and will be emailed to you.'),
            ],
        ], 202);
    }

    /**
     * Stream the customer's own generated export file via signed URL.
     */
    public function download(Request $request, string $export): StreamedResponse
    {
        abort_unless($export === $request->user()->ulid, 404);

        $path = "exports/{$export}.json";

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path);
    }

    /**
     * Request deletion of the account (FR-CUST-006).
     */
    public function requestDeletion(Request $request, RequestAccountDeletion $requestDeletion): JsonResponse
    {
        abort_unless($requestDeletion($request->user()), 403, __('This account cannot be deleted through self-service.'));

        return response()->json([
            'data' => [
                'message' => __('Your deletion request has been received and your access has been revoked.'),
            ],
        ]);
    }
}
