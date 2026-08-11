<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() against the RiskBoard aggregate in
 * DomainServiceProvider. Superadmin bypasses it through Gate::before.
 *
 * Only `viewAny`: a board is looked at, never created, edited or
 * deleted — its contents are derived, so there is nothing to authorize
 * beyond seeing it.
 */
class RiskBoardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('risk_board.view');
    }
}
