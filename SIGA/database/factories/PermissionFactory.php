<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = fake()->unique()->word();
        $action = fake()->randomElement([
            'create',
            'view',
            'edit',
            'delete',
            'search',
            'export_pdf',
            'export_excel',
        ]);

        return [
            'module' => $module,
            'action' => $action,
            'name' => "{$module}.{$action}",
        ];
    }
}
