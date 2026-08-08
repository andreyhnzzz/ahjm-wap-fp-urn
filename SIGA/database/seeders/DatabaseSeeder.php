<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
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
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'name' => 'prueba ISW-521',
            'email' => 'prueba@gmail.com',
            'password' => bcrypt('12345678'),
        ]);

        $user->roles()->sync(
            Role::query()->where('name', 'Superadmin')->pluck('id')
        );
    }
}
