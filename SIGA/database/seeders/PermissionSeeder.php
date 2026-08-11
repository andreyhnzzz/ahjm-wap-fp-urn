<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The seven actions a fully manageable CRUD module exposes.
     *
     * @var array<int, string>
     */
    private const CRUD_ACTIONS = [
        'create',
        'view',
        'edit',
        'delete',
        'search',
        'export_pdf',
        'export_excel',
    ];

    /**
     * Actions a read-only module exposes. A report is consulted and
     * downloaded, never created or deleted, so seeding it the full CRUD
     * set would litter the Roles screen with permissions that grant
     * nothing — `offer_reports.delete` has no code path behind it and
     * would only make the checklist harder to reason about.
     *
     * @var array<int, string>
     */
    private const REPORT_ACTIONS = [
        'view',
        'search',
        'export_pdf',
        'export_excel',
    ];

    /**
     * Every manageable module, mapped to the actions it actually
     * supports. Extend this list as new modules are added; the Policy of
     * each module reads these exact strings, so a typo here is a Policy
     * that silently always denies.
     *
     * @var array<string, array<int, string>>
     */
    private const MODULES = [
        'roles' => self::CRUD_ACTIONS,
        'permissions' => self::CRUD_ACTIONS,
        'teachers' => self::CRUD_ACTIONS,
        'classrooms' => self::CRUD_ACTIONS,
        'groups' => self::CRUD_ACTIONS,
        'offer_reports' => self::REPORT_ACTIONS,
        'teacher_load_reports' => self::REPORT_ACTIONS,
        // The risk board is a single live screen: nothing to create, and
        // nothing to export — its whole point is that it is never a
        // snapshot.
        'risk_board' => ['view'],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                Permission::query()->firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action],
                );
            }
        }
    }
}
