<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PermissionCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureGateBypass();
        $this->configurePermissionCacheInvalidation();
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
