<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ConfirmTwoFactor;
use App\Actions\Auth\DisableTwoFactor;
use App\Actions\Auth\EnableTwoFactor;
use App\Actions\Auth\LoginUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    /**
     * Begin two-factor enrollment.
     */
    public function enable(Request $request, EnableTwoFactor $enable): JsonResponse
    {
        return response()->json([
            'data' => $enable($request->user()),
        ]);
    }

    /**
     * Confirm enrollment with a TOTP code; returns one-time recovery codes.
     */
    public function confirm(TwoFactorConfirmRequest $request, ConfirmTwoFactor $confirm): JsonResponse
    {
        $recoveryCodes = $confirm($request->user(), $request->input('code'));

        return response()->json([
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    /**
     * Complete a login that was paused for a two-factor challenge.
     */
    public function challenge(TwoFactorChallengeRequest $request, LoginUser $loginUser): JsonResponse
    {
        $validated = $request->validated();

        $result = $loginUser->completeChallenge(
            $validated['challenge_token'],
            $validated['code'] ?? null,
            $validated['recovery_code'] ?? null,
            $validated['device_name'] ?? null,
            $request,
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    /**
     * Disable two-factor authentication after re-authentication.
     */
    public function disable(DisableTwoFactorRequest $request, DisableTwoFactor $disable): JsonResponse
    {
        $disable($request->user(), $request->input('password'));

        return response()->json([
            'data' => [
                'message' => __('Two-factor authentication disabled.'),
            ],
        ]);
    }
}
