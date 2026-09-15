<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Crear el permiso maestro 'gestion-proyectos.admin'
        $adminPerm = Permission::firstOrCreate([
            'name'       => 'gestion-proyectos.admin',
            'guard_name' => 'web',
        ]);

        // 2. Identificar permisos antiguos a consolidar
        $oldPermissions = [
            'gestion-proyectos.crear',
            'gestion-proyectos.editar',
            'gestion-proyectos.eliminar',
            'gestion-proyectos.equipos.ver',
            'gestion-proyectos.equipos.crear',
            'gestion-proyectos.equipos.eliminar',
            'gestion-proyectos.miembros.ver',
            'gestion-proyectos.miembros.gestionar',
            'equipos.ver',
            'equipos.crear',
            'equipos.eliminar',
        ];

        // 3. Otorgar el permiso maestro a todos los roles que tenían alguno de los antiguos
        $rolesWithOldPerms = DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('permissions.name', $oldPermissions)
            ->pluck('role_id')
            ->unique();

        foreach ($rolesWithOldPerms as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $role->givePermissionTo($adminPerm);
            }
        }

        // 4. Eliminar permisos antiguos
        Permission::whereIn('name', $oldPermissions)->delete();

        // 5. Sincronizar owner_id con gp_space_members
        $projects = DB::table('gp_projects')->get();
        foreach ($projects as $project) {
            if ($project->owner_id) {
                DB::table('gp_space_members')->updateOrInsert(
                    ['project_key' => $project->key, 'user_id' => $project->owner_id],
                    ['role' => 'propietario', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // El proceso de consolidación no es fácilmente reversible sin perder granularidad previa.
        // Se deja vacío para evitar destrucción accidental de la nueva estructura.
    }
};
