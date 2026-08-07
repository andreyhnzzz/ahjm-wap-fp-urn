<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Policies;

use App\Models\User;
use Src\IdentityAccess\Role\Domain\Entities\Role;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('roles.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.edit');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('roles.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('roles.export_excel');
    }
}
