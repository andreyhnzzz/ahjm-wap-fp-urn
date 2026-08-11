<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() against the TeacherLoadReport aggregate.
 * Superadmin bypasses it through Gate::before.
 */
class TeacherLoadReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('teacher_load_reports.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('teacher_load_reports.view');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('teacher_load_reports.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('teacher_load_reports.export_excel');
    }
}
