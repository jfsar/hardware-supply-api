<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Admin\BrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\FulfillmentController;
use App\Http\Controllers\Api\V1\Admin\InventoryController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ProductMediaController;
use App\Http\Controllers\Api\V1\Admin\RefundController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\WebhookController as AdminWebhookController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController as PublicProductController;
use App\Http\Controllers\Api\V1\Catalog\SearchAutocompleteController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\Engagement\AlertController;
use App\Http\Controllers\Api\V1\Engagement\ComparisonController;
use App\Http\Controllers\Api\V1\Engagement\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Engagement\RecentlyViewedController;
use App\Http\Controllers\Api\V1\Engagement\RecommendationController;
use App\Http\Controllers\Api\V1\Engagement\ReviewController;
use App\Http\Controllers\Api\V1\Engagement\WishlistController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\WebhookController;
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

// Cancel a deletion request inside its grace window — a signed link, no
// bearer token needed (FR-CUST-006, NFR-PRIV-001).
Route::get('/account/delete-request/{user}/cancel', [AccountController::class, 'cancelDeletion'])
    ->middleware('signed')
    ->name('account.delete.cancel');

// Inbound provider webhooks (Phase 5): unauthenticated, signature-verified,
// provider-aware rate limit. Raw body is consumed by the controller.
Route::post('/webhooks/payrex', WebhookController::class)
    ->middleware('throttle:webhooks');

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

    // Inventory administration (Phase 3).
    Route::get('/inventory', [InventoryController::class, 'index'])
        ->middleware('permission:inventory.view');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])
        ->middleware('permission:inventory.view');
    Route::post('/inventory/{variant}/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:inventory.adjust');

    // Payment refunds (Phase 5).
    Route::post('/payments/{payment}/refund', [RefundController::class, 'store'])
        ->middleware('permission:orders.refund');

    // Fulfillment (Phase 6 Task 4).
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])
        ->middleware('permission:orders.view');
    Route::post('/orders/{order}/fulfill', [FulfillmentController::class, 'fulfill'])
        ->middleware('permission:orders.fulfill');
    Route::patch('/shipments/{shipment}/tracking', [FulfillmentController::class, 'tracking'])
        ->middleware('permission:orders.fulfill');

    // Customer administration (Phase 8 Task 1, FR-ADMIN-002/003).
    Route::get('/customers', [AdminCustomerController::class, 'index'])
        ->middleware('permission:customers.view');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])
        ->middleware('permission:customers.view');
    Route::patch('/customers/{customer}', [AdminCustomerController::class, 'update'])
        ->middleware('permission:customers.update');
    Route::post('/customers/{customer}/suspend', [AdminCustomerController::class, 'suspend'])
        ->middleware('permission:customers.suspend');
    Route::post('/customers/{customer}/restore', [AdminCustomerController::class, 'restore'])
        ->middleware('permission:customers.suspend');

    // Order administration (Phase 8 Task 2, FR-ADMIN-004, SRS §69).
    Route::get('/orders', [AdminOrderController::class, 'index'])
        ->middleware('permission:orders.view');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])
        ->middleware('permission:orders.update');
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])
        ->middleware('permission:orders.cancel');
    Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])
        ->middleware('permission:orders.refund');
    Route::get('/orders/{order}/notes', [AdminOrderController::class, 'notesIndex'])
        ->middleware('permission:orders.notes');
    Route::post('/orders/{order}/notes', [AdminOrderController::class, 'notesStore'])
        ->middleware('permission:orders.notes');

    // Review moderation (Phase 8 Task 3, FR-ADMIN-005).
    Route::get('/reviews', [AdminReviewController::class, 'index'])
        ->middleware('permission:products.view');
    Route::get('/reviews/reports', [AdminReviewController::class, 'reports'])
        ->middleware('permission:products.view');
    Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])
        ->middleware('permission:products.update');
    Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])
        ->middleware('permission:products.update');
    Route::post('/reviews/{review}/hide', [AdminReviewController::class, 'hide'])
        ->middleware('permission:products.update');

    // Reports (Phase 8 Task 4, FR-RPT-001…005). Export routes must stay
    // before /reports/{reportType} so "exports" is not swallowed.
    Route::get('/reports/exports', [AdminReportController::class, 'index'])
        ->middleware('permission:reports.export');
    Route::post('/reports/exports', [AdminReportController::class, 'export'])
        ->middleware('permission:reports.export');
    Route::get('/reports/exports/{export}', [AdminReportController::class, 'show'])
        ->middleware('permission:reports.export');
    Route::get('/reports/{reportType}', [AdminReportController::class, 'query'])
        ->middleware('permission:reports.view');

    // Outbound webhooks (Phase 8 Task 6, FR-NOTIF-003/004).
    Route::get('/webhooks', [AdminWebhookController::class, 'index'])
        ->middleware('permission:webhooks.manage');
    Route::post('/webhooks', [AdminWebhookController::class, 'store'])
        ->middleware('permission:webhooks.manage');
    Route::get('/webhooks/{endpoint}', [AdminWebhookController::class, 'show'])
        ->middleware('permission:webhooks.manage');
    Route::patch('/webhooks/{endpoint}', [AdminWebhookController::class, 'update'])
        ->middleware('permission:webhooks.manage');
    Route::delete('/webhooks/{endpoint}', [AdminWebhookController::class, 'destroy'])
        ->middleware('permission:webhooks.manage');
    Route::get('/webhooks/{endpoint}/deliveries', [AdminWebhookController::class, 'deliveries'])
        ->middleware('permission:webhooks.manage');
});

// Signed report export download (Phase 8, FR-RPT-003) — no bearer token;
// the short-lived signature is the credential.
Route::get('/admin/reports/exports/{export}/download', [AdminReportController::class, 'download'])
    ->middleware('signed')
    ->name('admin.reports.exports.download');

// Public catalog browsing (Phase 2).
Route::prefix('search')->middleware('throttle:search')->group(function (): void {
    Route::get('/autocomplete', [SearchAutocompleteController::class, 'index']);
});

Route::get('/categories', [PublicCategoryController::class, 'index'])->middleware('throttle:search');
Route::get('/categories/{slug}', [PublicCategoryController::class, 'show'])->middleware('throttle:search');
Route::get('/products', [PublicProductController::class, 'index'])->middleware('throttle:search');

// Must be declared before the {slug} catch-alls below.
Route::get('/products/recently-viewed', [RecentlyViewedController::class, 'index'])
    ->middleware('throttle:engagement');

Route::get('/products/{slug}', [PublicProductController::class, 'show'])->middleware('throttle:search');
Route::get('/products/{slug}/related', [PublicProductController::class, 'related'])->middleware('throttle:search');
Route::get('/products/{slug}/reviews', [PublicProductController::class, 'reviews'])->middleware('throttle:search');

// Commerce (Phase 4): guest-accessible cart.
Route::prefix('cart')->middleware('throttle:cart')->group(function (): void {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'storeItem']);
    Route::patch('/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/items/{item}', [CartController::class, 'destroyItem']);
    Route::delete('/', [CartController::class, 'destroy']);
    Route::post('/coupon', [CartController::class, 'storeCoupon']);
    Route::delete('/coupon', [CartController::class, 'destroyCoupon']);
});

// Commerce (Phase 4): checkout (guest-allowed, idempotent).
Route::middleware('throttle:checkout')->group(function (): void {
    Route::post('/checkout/validate', [CheckoutController::class, 'validate'])
        ->middleware('idempotency:checkout.validate');
    Route::post('/checkout', [CheckoutController::class, 'place'])
        ->middleware('idempotency:checkout.place');
    Route::get('/checkout/{checkout}', [CheckoutController::class, 'show']);
});

// Commerce (Phase 4): customer orders (owner-scoped).
Route::prefix('orders')
    ->middleware(['auth:sanctum', 'verified', 'throttle:orders'])
    ->group(function (): void {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::get('/{order}/shipments', [OrderController::class, 'shipments']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])
            ->middleware('idempotency:orders.cancel');
        Route::post('/{order}/cancel-items', [OrderController::class, 'cancelItems'])
            ->middleware('idempotency:orders.cancel_items');
        Route::post('/{order}/payments', [PaymentController::class, 'store'])
            ->middleware('idempotency:orders.payments');
    });

// Customer engagement (Phase 7): verified-purchase reviews.
Route::middleware(['auth:sanctum', 'verified', 'throttle:engagement'])->group(function (): void {
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:reviews');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'helpful']);
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/items', [WishlistController::class, 'store']);
    Route::delete('/wishlist/items/{product_ulid}', [WishlistController::class, 'destroy']);

    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
});

// Customer engagement (Phase 7): guest-or-auth comparison.
Route::middleware('throttle:engagement')->group(function (): void {
    Route::get('/comparison', [ComparisonController::class, 'index']);
    Route::post('/comparison/items', [ComparisonController::class, 'store']);
    Route::delete('/comparison/items/{product_ulid}', [ComparisonController::class, 'destroy']);

    Route::post('/products/{variant}/stock-alerts', [AlertController::class, 'subscribeStock']);
    Route::delete('/products/{variant}/stock-alerts', [AlertController::class, 'unsubscribeStock']);
    Route::post('/products/{variant}/price-alerts', [AlertController::class, 'subscribePrice']);
    Route::delete('/products/{variant}/price-alerts', [AlertController::class, 'unsubscribePrice']);

    Route::get('/products/{slug}/recommendations', [RecommendationController::class, 'index']);
    Route::post('/products/{slug}/recommendations/click', [RecommendationController::class, 'click']);
});

// Commerce (Phase 5): gateway payment lifecycle (owner-scoped, idempotent).
Route::prefix('payments')
    ->middleware(['auth:sanctum', 'verified', 'throttle:orders'])
    ->group(function (): void {
        Route::post('/{payment}/retry', [PaymentController::class, 'retry'])
            ->middleware('idempotency:payments.retry');
        Route::post('/{payment}/cancel', [PaymentController::class, 'cancel'])
            ->middleware('idempotency:payments.cancel');
    });
