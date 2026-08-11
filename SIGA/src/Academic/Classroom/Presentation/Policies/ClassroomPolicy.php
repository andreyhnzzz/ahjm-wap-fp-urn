<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 */
class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('classrooms.export_excel');
    }
}
