<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ListUserSessions;
use App\Actions\Auth\RevokeUserSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SessionController extends Controller
{
    /**
     * List the authenticated user's active device sessions.
     */
    public function index(Request $request, ListUserSessions $listSessions): ResourceCollection
    {
        return SessionResource::collection($listSessions($request->user(), $request));
    }

    /**
     * Revoke one of the user's device sessions.
     */
    public function destroy(Request $request, UserSession $session, RevokeUserSession $revoke): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 404);

        $revoke($session);

        return response()->json([
            'data' => [
                'message' => __('Session revoked.'),
            ],
        ]);
    }
}
