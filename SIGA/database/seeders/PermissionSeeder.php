<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Actions available for every manageable module.
     *
     * @var array<int, string>
     */
    private const ACTIONS = [
        'create',
        'view',
        'edit',
        'delete',
        'search',
        'export_pdf',
        'export_excel',
    ];

    /**
     * Modules that currently expose the actions above.
     * Extend this list as new manageable modules are added.
     *
     * @var array<int, string>
     */
    private const MODULES = ['roles', 'permissions'];

    public function run(): void
    {
        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                Permission::query()->firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    ['module' => $module, 'action' => $action],
                );
            }
        }
    }
}
