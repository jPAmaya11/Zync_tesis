<?php

namespace Modules\GestionProyectos\Support;

use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;

/**
 * Centraliza la resolución de destinatarios de correo.
 *
 * Reglas (acordadas):
 *  - SCRUM: assignee + reporter + creator + aprobado_por.
 *  - El actor (quien provoca el cambio) NO recibe su propio correo.
 *  - Sin watchers, sin opt-out, todos los destinatarios reciben siempre.
 */
final class MailRecipients
{
    /**
     * @return string[] emails únicos, sin el del actor si se provee.
     */
    public static function forScrumTask(Proyecto $tarea, ?int $excludeUserId = null): array
    {
        $userIds = array_filter([
            $tarea->assignee_id,
            $tarea->reporter_id,
            $tarea->creator_id,
            $tarea->aprobado_por_id,
        ]);

        return self::userIdsToEmails($userIds, $excludeUserId);
    }

    /**
     * Reasignación: nuevo asignado + reporter/creator. NUNCA el assignee anterior.
     * @return string[]
     */
    public static function forScrumReassignment(Proyecto $tarea, ?int $excludeUserId = null): array
    {
        $userIds = array_filter([
            $tarea->assignee_id,
            $tarea->reporter_id,
            $tarea->creator_id,
        ]);

        return self::userIdsToEmails($userIds, $excludeUserId);
    }

    /**
     * @param int[] $userIds
     * @return string[]
     */
    public static function userIdsToEmails(array $userIds, ?int $excludeUserId = null): array
    {
        if ($excludeUserId !== null) {
            $userIds = array_filter($userIds, fn ($id) => (int) $id !== (int) $excludeUserId);
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Email único de un usuario por ID (helper para notificaciones puntuales como alta de miembro).
     */
    public static function userEmail(int $userId): ?string
    {
        $email = User::query()->whereKey($userId)->value('email');
        return (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }
}
