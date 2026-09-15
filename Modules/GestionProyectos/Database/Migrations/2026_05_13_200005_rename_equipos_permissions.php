<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renombra los permisos de equipos para seguir la convención del módulo
 * (gestion-proyectos.{accion}) y agrega los de miembros.
 *
 * Antes: equipos.ver / equipos.crear / equipos.eliminar
 * Después: gestion-proyectos.equipos.ver / gestion-proyectos.equipos.crear / ...
 *          gestion-proyectos.miembros.ver / gestion-proyectos.miembros.gestionar
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rename = [
            'equipos.ver'      => 'gestion-proyectos.equipos.ver',
            'equipos.crear'    => 'gestion-proyectos.equipos.crear',
            'equipos.eliminar' => 'gestion-proyectos.equipos.eliminar',
        ];

        foreach ($rename as $old => $new) {
            $perm = Permission::where('name', $old)->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->name = $new;
                $perm->save();
            } else {
                Permission::firstOrCreate(['name' => $new, 'guard_name' => 'web']);
            }
        }

        // Nuevos permisos de miembros del espacio
        foreach (['gestion-proyectos.miembros.ver', 'gestion-proyectos.miembros.gestionar'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Otorgar todos al rol admin
        $admin = \Spatie\Permission\Models\Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'gestion-proyectos.equipos.ver',
                'gestion-proyectos.equipos.crear',
                'gestion-proyectos.equipos.eliminar',
                'gestion-proyectos.miembros.ver',
                'gestion-proyectos.miembros.gestionar',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $revert = [
            'gestion-proyectos.equipos.ver'      => 'equipos.ver',
            'gestion-proyectos.equipos.crear'    => 'equipos.crear',
            'gestion-proyectos.equipos.eliminar' => 'equipos.eliminar',
        ];

        foreach ($revert as $new => $old) {
            Permission::where('name', $new)->where('guard_name', 'web')
                ->update(['name' => $old]);
        }

        Permission::whereIn('name', [
            'gestion-proyectos.miembros.ver',
            'gestion-proyectos.miembros.gestionar',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
