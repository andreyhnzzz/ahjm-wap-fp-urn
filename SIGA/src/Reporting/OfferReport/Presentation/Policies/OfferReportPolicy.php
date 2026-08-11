<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() against the OfferReport aggregate.
 * Superadmin bypasses it through Gate::before.
 *
 * A report is consulted and downloaded — there is deliberately no
 * create/update/delete here, matching the `offer_reports` action set in
 * PermissionSeeder.
 */
class OfferReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('offer_reports.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('offer_reports.view');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('offer_reports.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('offer_reports.export_excel');
    }
}
