<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PermissionCache
{
    private const VERSION_KEY = 'user_perms:version';

    /**
     * The current permission-map version used to key per-user caches.
     */
    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Invalidate every cached user permission map by rotating the version.
     */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
