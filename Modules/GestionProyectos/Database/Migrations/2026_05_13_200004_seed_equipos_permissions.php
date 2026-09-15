<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos para el módulo de Equipos:
 *   equipos.ver      — listar equipos del espacio
 *   equipos.crear    — crear equipos + gestionar miembros
 *   equipos.eliminar — eliminar equipos
 *
 * Los 3 se otorgan al rol admin automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'equipos.ver',
            'equipos.crear',
            'equipos.eliminar',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name'       => $perm,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'equipos.ver',
            'equipos.crear',
            'equipos.eliminar',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
