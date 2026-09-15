<?php

namespace Modules\GestionProyectos\Mcp\Concerns;

use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\GestionProyectos\Services\DTOs\ProyectoDTO;
use Modules\User\Models\User;

/**
 * Glue compartido por las tools MCP. Replica los helpers privados del
 * ScrumApiController (resolución de account_id, serialización DTO sin
 * custom_fields, chequeo de acceso por espacio) SIN tocar el controller
 * ni la lógica existente. La lógica real (roles/estados) vive en el service.
 */
trait InteractsWithIssues
{
    protected function service(): ProyectoServiceInterface
    {
        return app(ProyectoServiceInterface::class);
    }

    /** Usuario autenticado por el token (lo deja AuthGpApiToken vía Auth::setUser). */
    protected function user()
    {
        return auth()->user();
    }

    /** Serializa al shape del DTO, omitiendo custom_fields (igual que la API REST). */
    protected function toApi(Proyecto $p): array
    {
        $arr = ProyectoDTO::fromModel($p);
        unset($arr['custom_fields']);

        return $arr;
    }

    /** Carga las relaciones que necesita el DTO para una respuesta completa. */
    protected function loadForDto(Proyecto $p): Proyecto
    {
        return $p->load(['assignee', 'reporter', 'creator', 'aprobadoPor', 'team'])
                 ->loadCount('subTareas');
    }

    /** account_id (string) → user_id (int). Sin validación de existencia. */
    protected function resolveUserId(?string $accountId): ?int
    {
        if ($accountId === null || $accountId === '' || $accountId === 'null') {
            return null;
        }
        $id = (int) $accountId;

        return $id > 0 ? $id : null;
    }

    /**
     * Resuelve account_id a user_id y valida que el usuario exista en BD.
     *
     * @return array{0: int|null, 1: string|null}  [userId, errorMessage]
     *   - [null, null]         → accountId vacío/null (= desasignar)
     *   - [int,  null]         → OK, usuario encontrado
     *   - [null, string]       → inválido o no existe
     */
    protected function resolveAndValidateUserId(?string $accountId, string $label = 'usuario'): array
    {
        if ($accountId === null || $accountId === '' || $accountId === 'null') {
            return [null, null];
        }
        $id = (int) $accountId;
        if ($id <= 0) {
            return [null, "account_id inválido para {$label}: \"{$accountId}\""];
        }
        if (! User::where('id', $id)->exists()) {
            return [null, "El {$label} no existe (account_id: {$accountId})."];
        }

        return [$id, null];
    }

    /** ¿El usuario puede ver ese espacio? Admin global, miembro, o propietario. */
    protected function canAccessProject($user, string $projectKey): bool
    {
        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) {
            return true;
        }
        if (GpSpaceMember::roleInSpace($user->id, $projectKey) !== null) {
            return true;
        }

        return GpProject::where('key', $projectKey)->where('owner_id', $user->id)->exists();
    }
}
