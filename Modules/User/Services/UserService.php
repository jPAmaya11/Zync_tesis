<?php

namespace Modules\User\Services;

use Modules\User\Services\Contracts\UserServiceInterface;
use Modules\User\Models\User;
use Modules\Role\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserService implements UserServiceInterface
{
    /**
     * Obtener usuarios paginados y filtrados
     *
     * @param string|null $search - Búsqueda simple (compatibilidad hacia atrás)
     * @param int $perPage - Items por página
     * @param callable|null $filterCallback - Callback para aplicar filtros avanzados
     * @return array
     */
    public function getUsersPaginatedWithEffectiveData($search = null, $perPage = 20, ?callable $filterCallback = null)
    {
        $query = User::with([
            'roles',
            'creador:id,name',
        ]);

        // Aplicar filtros avanzados si se proporciona callback
        if ($filterCallback) {
            $query = $filterCallback($query);
        }
        // Si no hay callback pero hay búsqueda simple, aplicar búsqueda básica (compatibilidad)
        elseif ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('numero_documento', 'like', "%$search%");
            });
        }

        $paginator = $query->orderBy('id', 'asc')->paginate($perPage)->withQueryString();

        return [
            'data' => $paginator->getCollection()->map(fn ($user) => $user->toArray()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Crear un nuevo usuario - Implementación de la interfaz
     */
    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'numero_documento' => $data['numero_documento'] ?? null,
            'active' => $data['active'] ?? true,
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? null,
            'phone' => $data['phone'] ?? null,
            // Quién crea al usuario (para mostrar "Creado por" en la tabla).
            'created_by' => auth()->id(),
        ]);

        // Sincronizar roles si vienen en el payload (consistente con updateUser)
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
            $this->auditarRolesUsuario($user, []); // usuario nuevo: antes no tenía roles
        }

        return $user;
    }

    /**
     * Actualizar un usuario - Implementación de la interfaz
     */
    public function updateUser(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'numero_documento' => $data['numero_documento'] ?? $user->numero_documento,
            'active' => $data['active'] ?? $user->active,
            'department' => $data['department'] ?? $user->department,
            'position' => $data['position'] ?? $user->position,
            'phone' => $data['phone'] ?? $user->phone,
        ]);

        // Actualizar contraseña solo si se proporciona
        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        // Actualizar roles si se proporcionan
        if (isset($data['roles'])) {
            $beforeIds = $user->roles()->pluck('roles.id')->all();
            $user->syncRoles($data['roles']);
            $this->auditarRolesUsuario($user, $beforeIds);
        }

        return $user;
    }

    /**
     * Auditoría: registra qué roles se asignaron/removieron a un usuario
     * comparando los ids previos con los actuales.
     *
     * @param array<int,int> $beforeIds  ids de roles que tenía antes del sync
     */
    private function auditarRolesUsuario(User $user, array $beforeIds): void
    {
        $afterIds   = $user->roles()->pluck('roles.id')->all();
        $addedIds   = array_diff($afterIds, $beforeIds);
        $removedIds = array_diff($beforeIds, $afterIds);

        if (! $addedIds && ! $removedIds) {
            return;
        }

        foreach (\Modules\Role\Models\Role::whereIn('id', $addedIds)->get() as $rol) {
            \Modules\Role\Services\RoleAuditLogger::userAssigned($rol, $user);
        }
        foreach (\Modules\Role\Models\Role::whereIn('id', $removedIds)->get() as $rol) {
            \Modules\Role\Services\RoleAuditLogger::userUnassigned($rol, $user);
        }
    }

    /**
     * Eliminar un usuario - Implementación de la interfaz
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Cambiar estado de un usuario - Implementación de la interfaz
     */
    public function toggleUserStatus(User $user): User
    {
        $user->update(['active' => !$user->active]);
        return $user;
    }

    /**
     * Cambiar contraseña - Implementación de la interfaz
     */
    public function changePassword(User $user, string $newPassword): User
    {
        $user->update(['password' => Hash::make($newPassword)]);
        return $user;
    }

    /**
     * Obtener estadísticas - Implementación de la interfaz
     */
    public function getEstadisticas(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'users_with_roles' => User::has('roles')->count(),
            'recent_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'users_by_role' => Role::withCount('users')->get()->pluck('users_count', 'name'),
        ];
    }

    /**
     * Obtener datos para formularios - Implementación de la interfaz
     */
    public function getFormData(): array
    {
        return [
            'roles' => Role::orderBy('name', 'asc')->get(),
        ];
    }

    /**
     * Obtener todos los usuarios con sus roles
     */
    public function getUsersWithEffectiveData()
    {
        return User::with('roles')->get()->map(fn ($user) => $user->toArray());
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getUserStatistics()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'users_with_roles' => User::has('roles')->count(),
            'recent_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
