<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Policies;

use App\Models\User;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissions.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('permissions.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('permissions.create');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissions.edit');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissions.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('permissions.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('permissions.export_excel');
    }
}
