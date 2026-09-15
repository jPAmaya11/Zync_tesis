<?php

namespace Modules\User\Services\Contracts;

use Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    /**
     * Obtener usuarios con datos efectivos (herencia de roles)
     */
    public function getUsersWithEffectiveData();

    /**
     * Obtener usuarios paginados con datos efectivos y filtros opcionales
     *
     * @param string|null $search - Término de búsqueda simple
     * @param int $perPage - Items por página
     * @param callable|null $filterCallback - Callback para aplicar filtros avanzados
     * @return array
     */
    public function getUsersPaginatedWithEffectiveData(
        $search = null,
        $perPage = 20,
        ?callable $filterCallback = null
    );

    /**
     * Obtener datos para formularios (roles)
     */
    public function getFormData(): array;

    /**
     * Crear un nuevo usuario
     */
    public function createUser(array $data): User;

    /**
     * Actualizar un usuario existente
     */
    public function updateUser(User $user, array $data): User;

    /**
     * Eliminar un usuario
     */
    public function deleteUser(User $user): bool;

    /**
     * Obtener estadísticas de usuarios
     */
    public function getEstadisticas(): array;

    /**
     * Cambiar estado activo/inactivo de un usuario
     */
    public function toggleUserStatus(User $user): User;

    /**
     * Cambiar contraseña de un usuario
     */
    public function changePassword(User $user, string $newPassword): User;
}
