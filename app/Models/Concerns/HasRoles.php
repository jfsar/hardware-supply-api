<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Services\PermissionCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

trait HasRoles
{
    /**
     * The administrative roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot('created_at');
    }

    /**
     * Whether the user holds the given role slug.
     */
    public function hasRole(string $slug): bool
    {
        return isset($this->permissionMap()['roles'][$slug]);
    }

    /**
     * Whether any of the user's roles grants the given permission slug.
     */
    public function hasPermissionTo(string $slug): bool
    {
        return isset($this->permissionMap()['permissions'][$slug]);
    }

    /**
     * The cached role and permission slug maps for this user.
     *
     * @return array{roles: array<string, true>, permissions: array<string, true>}
     */
    protected function permissionMap(): array
    {
        $key = sprintf(
            'user_perms:v%d:%d',
            PermissionCache::version(),
            $this->getKey(),
        );

        return Cache::remember($key, now()->addHour(), function (): array {
            $roles = $this->roles()->get(['roles.id', 'roles.slug']);

            $permissions = $roles
                ->flatMap(fn (Role $role) => $role->permissions()->get(['permissions.slug']))
                ->unique('slug');

            return [
                'roles' => $roles->mapWithKeys(fn (Role $role) => [$role->slug => true])->all(),
                'permissions' => $permissions->mapWithKeys(fn ($permission) => [$permission->slug => true])->all(),
            ];
        });
    }
}
