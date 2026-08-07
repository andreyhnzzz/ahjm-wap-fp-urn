<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = Role::query()->firstOrCreate(['name' => 'Superadmin']);
        $superadmin->permissions()->sync(Permission::query()->pluck('id'));
        Role::query()->firstOrCreate(['name' => 'Admin']);
    }
}
