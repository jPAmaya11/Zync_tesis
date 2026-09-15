<?php

namespace Modules\GestionProyectos\Services\Contracts;

use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;

interface ProyectoServiceInterface
{
    /**
     * Obtener listado paginado con filtros.
     *
     * @param  array $filters [project, statuses[], assignees[], priorities[], labels[], issue_types[], search, order_by]
     * @param  int   $page
     * @param  int   $perPage
     * @return array{data: array, current_page: int, last_page: int, total: int, per_page: int}
     */
    public function getPaginated(array $filters, int $page = 1, int $perPage = 50): array;

    /**
     * Crear un nuevo proyecto/issue.
     *
     * @param  array $data
     * @return Proyecto
     */
    public function create(array $data): Proyecto;

    /**
     * Actualizar campos de un proyecto/issue por su key.
     *
     * @param  string     $key
     * @param  array      $fields
     * @param  User|null  $actor  Si null, usa auth()->user(). Pasar explícito en jobs/CLI/webhooks.
     * @return Proyecto
     */
    public function update(string $key, array $fields, ?User $actor = null): Proyecto;

    /**
     * Eliminar un proyecto/issue por su key (soft delete).
     *
     * @param  string     $key
     * @param  User|null  $actor  Si null, usa auth()->user().
     * @return void
     */
    public function delete(string $key, ?User $actor = null): void;

    /**
     * Buscar usuarios asignables por nombre o email.
     *
     * @param  string $projectKey
     * @param  string $query
     * @return array  [['account_id', 'display_name', 'email', 'avatar_url'], ...]
     */
    public function getAssignableUsers(string $projectKey = '', string $query = ''): array;

    /**
     * @param string[] $projectKeys
     * @return array  [['account_id', 'display_name', 'email', 'avatar_url'], ...]
     */
    public function getAssignableUsersForSpaces(array $projectKeys, string $query = ''): array;

    /**
     * Obtener tipos de issue disponibles.
     *
     * @return array  [['id', 'name'], ...]
     */
    public function getIssueTypes(): array;

    /**
     * Obtener proyectos disponibles.
     *
     * @return array  [['id', 'key', 'name', 'avatar_url'], ...]
     */
    public function getProjects(): array;

    /**
     * Obtener estados disponibles.
     *
     * @return array  lista de strings
     */
    public function getStatuses(): array;

    /**
     * Obtener prioridades disponibles.
     *
     * @return array  lista de strings
     */
    public function getPriorities(): array;

    /**
     * Obtener etiquetas usadas en la base de datos (únicas).
     *
     * @return array lista de strings
     */
    public function getLabels(): array;

    /**
     * Obtener transiciones de estado disponibles para un proyecto.
     *
     * @param  string $key   Issue key
     * @return array  [['id', 'name', 'to'], ...]
     */
    public function getTransitions(string $key): array;

    /**
     * Actualización masiva de tareas.
     *
     * @param array      $keys
     * @param array      $fields
     * @param array|null $labelIds
     * @param User|null  $actor  Si null, usa auth()->user().
     * @return array{succeeded: array, failed: array}
     */
    public function bulkUpdate(array $keys, array $fields, ?array $labelIds = null, ?User $actor = null): array;

    /**
     * Eliminación masiva de tareas.
     *
     * @param array     $keys
     * @param User|null $actor Si null, usa auth()->user().
     * @return array{succeeded: array, failed: array}
     */
    public function bulkDelete(array $keys, ?User $actor = null): array;
}
