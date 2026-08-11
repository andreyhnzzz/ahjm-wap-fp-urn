<?php

declare(strict_types=1);

namespace Src\Academic\Group\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 */
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('groups.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('groups.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('groups.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('groups.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('groups.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('groups.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('groups.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('groups.export_excel');
    }
}
