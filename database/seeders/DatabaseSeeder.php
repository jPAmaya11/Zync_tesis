<?php

namespace Database\Seeders;

use Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Modules\Role\Models\Role;
use Modules\Role\Database\Seeders\PermissionSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles y permisos
        $this->call([
            PermissionSeeder::class,
        ]);

        // 2. Usuario administrador inicial
        $admin = User::firstOrCreate(
            ['email' => 'admin@zync.test'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'active' => true,
            ]
        );

        if ($rolAdmin = Role::where('name', 'admin')->first()) {
            $admin->assignRole($rolAdmin);
        }
    }
}
