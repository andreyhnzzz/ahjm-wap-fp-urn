<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 *
 * The permission names must match PermissionSeeder's `teachers` module
 * letter for letter — that seeder is the single source of truth for what
 * these strings may be.
 */
class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('teachers.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('teachers.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('teachers.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('teachers.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('teachers.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('teachers.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('teachers.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('teachers.export_excel');
    }
}
