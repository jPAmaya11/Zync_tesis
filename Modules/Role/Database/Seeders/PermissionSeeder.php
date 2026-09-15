<?php

namespace Modules\Role\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Role\Models\Module;
use Modules\Role\Models\Permission;
use Modules\Role\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modulosConPermisos = [
            'Roles' => ['ver', 'crear', 'editar', 'eliminar'],
            'Usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
            'Gestion Proyectos' => [
                'ver',      // acceso de lectura al módulo
                'miembro',  // puede ser miembro de un espacio o proyecto (requiere ver)
                'admin',    // administra el módulo: tareas, equipos, miembros
            ],
        ];

        foreach ($modulosConPermisos as $moduloNombre => $acciones) {
            $modulo = Module::firstOrCreate(['name' => $moduloNombre]);
            $moduloSlug = strtolower(str_replace(' ', '-', $moduloNombre));

            foreach ($acciones as $accion) {
                Permission::updateOrCreate(
                    ['name' => "{$moduloSlug}.{$accion}", 'guard_name' => 'web'],
                    ['module_id' => $modulo->id]
                );
            }
        }

        // ROL ADMIN — acceso total
        $rolAdmin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $rolAdmin->syncPermissions(Permission::all());

        // ROL COLABORADOR — entra al módulo y participa en espacios/proyectos,
        // pero no administra el módulo ni gestiona usuarios/roles.
        $rolColaborador = Role::firstOrCreate([
            'name' => 'colaborador',
            'guard_name' => 'web',
        ]);
        $rolColaborador->syncPermissions([
            'gestion-proyectos.ver',
            'gestion-proyectos.miembro',
        ]);
    }
}
