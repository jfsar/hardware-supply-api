<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Admin\BrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ProductMediaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController as PublicProductController;
use App\Http\Controllers\Api\V1\Catalog\SearchAutocompleteController;
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

// Admin catalog management (Phase 2).
Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:admin'])->group(function (): void {
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.view');
    Route::patch('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete');

    Route::post('/products/{product}/publish', [ProductController::class, 'publish'])
        ->middleware('permission:products.publish');
    Route::post('/products/{product}/unpublish', [ProductController::class, 'unpublish'])
        ->middleware('permission:products.publish');

    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])
        ->middleware('permission:products.delete');

    Route::post('/products/{product}/images', [ProductMediaController::class, 'storeImage'])
        ->middleware('permission:products.update');
    Route::delete('/products/{product}/images/{image}', [ProductMediaController::class, 'destroyImage'])
        ->middleware('permission:products.update');
    Route::post('/products/{product}/documents', [ProductMediaController::class, 'storeDocument'])
        ->middleware('permission:products.update');
    Route::delete('/products/{product}/documents/{document}', [ProductMediaController::class, 'destroyDocument'])
        ->middleware('permission:products.update');

    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middleware('permission:categories.manage');
    Route::apiResource('brands', BrandController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middleware('permission:brands.manage');
});

// Public catalog browsing (Phase 2).
Route::prefix('search')->middleware('throttle:search')->group(function (): void {
    Route::get('/autocomplete', [SearchAutocompleteController::class, 'index']);
});

Route::get('/categories', [PublicCategoryController::class, 'index'])->middleware('throttle:search');
Route::get('/categories/{slug}', [PublicCategoryController::class, 'show'])->middleware('throttle:search');
Route::get('/products', [PublicProductController::class, 'index'])->middleware('throttle:search');
Route::get('/products/{slug}', [PublicProductController::class, 'show'])->middleware('throttle:search');
Route::get('/products/{slug}/related', [PublicProductController::class, 'related'])->middleware('throttle:search');
Route::get('/products/{slug}/reviews', [PublicProductController::class, 'reviews'])->middleware('throttle:search');
