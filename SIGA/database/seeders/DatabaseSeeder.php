<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $superadminUser = User::factory()->create([
            'name' => 'prueba ISW-521',
            'email' => 'prueba@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        $superadminUser->roles()->sync(
            Role::query()->where('name', 'Superadmin')->pluck('id')
        );

        $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();
        $adminRole->permissions()->sync(Permission::query()->pluck('id'));

        $adminUser = User::factory()->create([
            'name' => 'admin prueba ISW-521',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        $adminUser->roles()->sync([$adminRole->id]);
    }
}
