<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    // Public authentication endpoints.
    Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
    Route::middleware('throttle:auth')->post('/login', [AuthController::class, 'login']);
    Route::middleware('throttle:auth')->post('/2fa/challenge', [TwoFactorController::class, 'challenge']);
    Route::middleware('throttle:password-reset')->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::middleware('throttle:password-reset')->post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');

    // Authenticated endpoints.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::middleware('throttle:verification')->post('/resend-verification', [AuthController::class, 'resendVerification']);

        Route::middleware('verified')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/sessions', [SessionController::class, 'index']);
            Route::delete('/sessions/{session}', [SessionController::class, 'destroy']);

            Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
            Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
            Route::delete('/2fa', [TwoFactorController::class, 'disable']);

            Route::middleware('throttle:account')->patch('/me', [ProfileController::class, 'update']);
        });
    });
});

// Customer saved address (SRS §36: /api/v1/address) and account self-service.
Route::middleware(['auth:sanctum', 'verified', 'throttle:account'])->group(function (): void {
    Route::get('/address', [AddressController::class, 'show'])->name('address.show');
    Route::put('/address', [AddressController::class, 'update'])->name('address.update');
    Route::delete('/address', [AddressController::class, 'destroy'])->name('address.destroy');

    Route::post('/account/export', [AccountController::class, 'requestExport'])->name('account.export.request');
    Route::get('/account/export/{export}', [AccountController::class, 'download'])
        ->middleware('signed')
        ->name('account.export.download');
    Route::post('/account/delete-request', [AccountController::class, 'requestDeletion'])->name('account.delete.request');
});
