<?php

namespace App\Providers;

use App\Contracts\ProductSearch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\InventoryObserver;
use App\Services\PermissionCache;
use App\Services\Search\MySqlProductSearch;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProductSearch::class, MySqlProductSearch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureGateBypass();
        $this->configurePermissionCacheInvalidation();
        $this->configureProductBinding();
        $this->configureVariantBinding();
        $this->configureInventoryObserver();
        $this->configureScrambleDocumentation();
    }

    /**
     * Admin routes resolve products by ULID including archived ones (FR-CAT-010).
     */
    private function configureProductBinding(): void
    {
        Route::bind('product', fn (string $value): Product => Product::withTrashed()
            ->where('ulid', $value)
            ->firstOrFail());
    }

    /**
     * Inventory routes resolve variants by ULID including archived ones.
     */
    private function configureVariantBinding(): void
    {
        Route::bind('variant', fn (string $value): ProductVariant => ProductVariant::withTrashed()
            ->where('ulid', $value)
            ->firstOrFail());
    }

    /**
     * Every new variant gets a zero-quantity stock row at the primary warehouse.
     */
    private function configureInventoryObserver(): void
    {
        ProductVariant::observe(InventoryObserver::class);
    }

    /**
     * Group documented operations into stable tags by URI segment.
     */
    private function configureScrambleDocumentation(): void
    {
        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo, Operation $operation): array {
            $uri = $routeInfo->route->uri();

            return match (true) {
                str_starts_with($uri, 'api/v1/admin') => ['Admin · Catalog'],
                str_starts_with($uri, 'api/v1/auth/2fa') => ['Auth · Two-Factor'],
                str_starts_with($uri, 'api/v1/auth/sessions') => ['Auth · Sessions'],
                str_starts_with($uri, 'api/v1/auth') => ['Auth'],
                str_starts_with($uri, 'api/v1/account') => ['Account'],
                str_starts_with($uri, 'api/v1/address') => ['Account · Address'],
                str_starts_with($uri, 'api/v1/search') => ['Catalog · Search'],
                default => ['Catalog'],
            };
        });
    }

    /**
     * Named rate limiters for authentication endpoints.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(3)
            ->by(optional($request->user())->id ?? $request->ip()));

        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(30)
            ->by(optional($request->user())->id ?? $request->ip()));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(60)
            ->by(optional($request->user())->id ?? $request->ip()));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }

    /**
     * Super administrators pass every authorization check.
     */
    private function configureGateBypass(): void
    {
        Gate::before(function ($user): ?bool {
            return $user instanceof User && $user->hasRole('super_admin')
                ? true
                : null;
        });
    }

    /**
     * Rotate the permission-map version whenever role or permission
     * assignments change so cached user maps invalidate immediately.
     */
    private function configurePermissionCacheInvalidation(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (preg_match(
                '/^\s*(insert\s+into|update|delete\s+from)\s+["`\[]?(role_user|permission_role)["`\]]?/i',
                (string) $query->sql,
            ) === 1) {
                PermissionCache::bump();
            }
        });
    }
}
