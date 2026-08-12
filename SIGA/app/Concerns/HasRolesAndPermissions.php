<?php

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Dependency-free Role & Permission behavior for User, covering both
 * permissions inherited through a role and permissions granted directly.
 *
 * @mixin Model
 *
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
trait HasRolesAndPermissions
{
    /**
     * Flattened "permission name => true" lookup, built once per request.
     * Null until the first check forces it to load.
     *
     * @var array<string, true>|null
     */
    private ?array $permissionNameSet = null;

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

    public function hasPermissionTo(string $permission): bool
    {
        return isset($this->permissionNameSet()[$permission]);
    }

    /**
     * Every permission the user holds, granted directly or inherited
     * through a role, as an O(1) lookup set.
     *
     * A page renders one @can per sidebar entry plus one per row action,
     * so this is read tens of times per request. Resolving it per check
     * meant one query per role and a nested collection scan every time;
     * both relations are loaded here in a single pass instead, and the
     * flattened result is reused for the rest of the request.
     *
     * @return array<string, true>
     */
    private function permissionNameSet(): array
    {
        if ($this->permissionNameSet !== null) {
            return $this->permissionNameSet;
        }

        $this->loadMissing(['permissions:id,name', 'roles.permissions:id,name']);

        $names = $this->permissions->pluck('name')->all();

        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $names[] = $permission->name;
            }
        }

        return $this->permissionNameSet = array_fill_keys($names, true);
    }
}
