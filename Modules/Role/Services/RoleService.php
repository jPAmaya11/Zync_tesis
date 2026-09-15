<?php

namespace Modules\Role\Services;

use Modules\Role\Services\Contracts\RoleServiceInterface;
use Modules\Role\Models\Role;
use Modules\Role\Models\Permission;
use Modules\Role\Services\RoleAuditLogger;
use Illuminate\Support\Facades\Auth;

class RoleService implements RoleServiceInterface
{
    /**
     * Obtener roles con relaciones
     */
    public function getRolesWithRelations(string $search = null)
    {
        $query = Role::with('permissions')
            ->withCount('users'); // users_count: nº de usuarios con el rol (badge en la tarjeta)

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $query->get();
    }

    /**
     * Obtener datos para formularios
     */
    public function getFormData(): array
    {
        return [
            'permissions' => Permission::with('module')->get(),
        ];
    }

    /**
     * Crear nuevo rol
     */
    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $this->syncRoleRelations($role, $data);

        $role->refresh();
        RoleAuditLogger::created($role, [
            'name'     => $role->name,
            'permisos' => $role->permissions()->pluck('name')->all(),
        ]);

        return $role;
    }

    /**
     * Actualizar rol existente
     */
    public function updateRole(Role $role, array $data): Role
    {
        $before = $this->snapshotRol($role);

        $role->update([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $this->syncRoleRelations($role, $data);

        $role->refresh();
        $this->auditarCambios($role, $before, $this->snapshotRol($role));

        return $role;
    }

    /**
     * Eliminar rol
     */
    public function deleteRole(Role $role): bool
    {
        RoleAuditLogger::deleted($role);

        return $role->delete();
    }

    /** Estado del rol (nombre + relaciones) para comparar antes/después. */
    private function snapshotRol(Role $role): array
    {
        return [
            'name'        => $role->name,
            'permissions' => $role->permissions()->pluck('name')->all(),
        ];
    }

    /** Compara antes/después y registra un evento de auditoría por categoría cambiada. */
    private function auditarCambios(Role $role, array $before, array $after): void
    {
        if ($before['name'] !== $after['name']) {
            RoleAuditLogger::nameChanged($role, $before['name'], $after['name']);
        }

        $permAdd = array_values(array_diff($after['permissions'], $before['permissions']));
        $permRem = array_values(array_diff($before['permissions'], $after['permissions']));
        if ($permAdd || $permRem) {
            RoleAuditLogger::permissionsChanged($role, $permAdd, $permRem);
        }
    }

    /**
     * Obtener estadísticas de roles
     */
    public function getEstadisticas(): array
    {
        return [
            'total_roles' => Role::count(),
            'roles_con_permisos' => Role::has('permissions')->count(),
            'permisos_mas_usados' => Permission::withCount('roles')
                ->orderBy('roles_count', 'desc')
                ->limit(5)
                ->get()
                ->pluck('roles_count', 'name'),
        ];
    }

    /**
     * Sincronizar relaciones del rol
     */
    private function syncRoleRelations(Role $role, array $data): void
    {
        // Sincronizar permisos aplicando el tope por actor: un no-admin solo puede
        // tocar permisos dentro de su alcance; los del rol fuera de su alcance se preservan.
        $role->syncPermissions($this->permisosPermitidos($role, $data['permissions'] ?? []));
    }

    /**
     * Permisos finales a guardar según el actor ("no puedes otorgar lo que no tienes"):
     *  - admin (o sin sesión: seeders/consola): sin tope.
     *  - no-admin: solo puede tocar permisos DENTRO de su alcance; los permisos del rol
     *    FUERA de su alcance se preservan intactos (no se degradan roles más amplios).
     *
     * @param  array<int,string>  $enviados  nombres de permisos enviados
     * @return array<int,string>            nombres de permisos finales
     */
    private function permisosPermitidos(Role $role, array $enviados): array
    {
        $actor = Auth::user();

        if (! $actor || $actor->hasRole('admin')) {
            return $enviados;
        }

        $propios    = $actor->getAllPermissions()->pluck('name')->all();
        $existentes = $role->permissions()->pluck('name')->all();

        $fueraDeAlcance  = array_diff($existentes, $propios);   // preservar lo que no ve
        $dentroDeAlcance = array_intersect($enviados, $propios); // solo lo suyo del envío

        return array_values(array_unique(array_merge($fueraDeAlcance, $dentroDeAlcance)));
    }
}
