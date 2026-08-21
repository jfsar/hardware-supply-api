<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResendEmailVerification;
use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Actions\Auth\VerifyUserEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Register a new customer account.
     */
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser($request->validated());

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    /**
     * Authenticate and issue a device session.
     */
    public function login(LoginRequest $request, LoginUser $loginUser): JsonResponse
    {
        $validated = $request->validated();

        $result = $loginUser(
            $validated['email'],
            $validated['password'],
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
     * Revoke the current session.
     */
    public function logout(Request $request, LogoutUser $logoutUser): JsonResponse
    {
        $logoutUser($request->user(), $request->bearerToken());

        return response()->json([
            'data' => [
                'message' => __('Logged out.'),
            ],
        ]);
    }

    /**
     * The authenticated user's profile.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Request a password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request, SendPasswordResetLink $sendLink): JsonResponse
    {
        $sendLink($request->input('email'));

        return response()->json([
            'data' => [
                'message' => __('If the email address exists, a reset link has been sent.'),
            ],
        ]);
    }

    /**
     * Consume a reset token and set a new password.
     */
    public function resetPassword(ResetPasswordRequest $request, ResetUserPassword $resetPassword): JsonResponse
    {
        $validated = $request->validated();

        $resetPassword($validated['email'], $validated['password'], $validated['token']);

        return response()->json([
            'data' => [
                'message' => __('Password has been reset.'),
            ],
        ]);
    }

    /**
     * Re-send the verification email for the authenticated account.
     */
    public function resendVerification(Request $request, ResendEmailVerification $resend): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            abort(409, __('Email address is already verified.'));
        }

        $resend($user);

        return response()->json([
            'data' => [
                'message' => __('Verification link sent.'),
            ],
        ]);
    }

    /**
     * Verify an email address from a signed link.
     */
    public function verifyEmail(string $id, string $hash, VerifyUserEmail $verify): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! $user->hasVerifiedEmail() && ! $verify($user, $hash)) {
            abort(403, __('Invalid email verification link.'));
        }

        return response()->json([
            'data' => [
                'message' => __('Email address verified.'),
            ],
        ]);
    }
}
