<?php

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Spatie-inspired, dependency-free Role & Permission behavior for User.
 * Supports permissions inherited through roles AND permissions granted
 * directly to a user, mirroring laravel-permission's public API surface.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
trait HasRolesAndPermissions
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }
    /**
     * @param array<int, string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function assignRole(string ...$roles): void
    {
        $ids = Role::query()->whereIn('name', $roles)->pluck('id');
        $this->roles()->syncWithoutDetaching($ids);
    }

    /**
     * @param array<int, string> $roles
     */
    public function syncRoles(array $roles): void
    {
        $ids = Role::query()->whereIn('name', $roles)->pluck('id');
        $this->roles()->sync($ids);
    }

    public function givePermissionTo(string ...$permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function revokePermissionTo(string ...$permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $this->permissions()->detach($ids);
    }

    public function hasDirectPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }


    public function hasPermissionTo(string $permission): bool
    {
        if ($this->hasDirectPermission($permission)) {
            return true;
        }

        return $this->roles->contains(
            fn(Role $role) => $role->permissions->contains('name', $permission)
        );
    }

    /**
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
