<?php

namespace Modules\GestionProyectos\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;

/**
 * Registra en gp_audit_log los cambios de campos críticos de una tarea.
 *
 * Campos rastreados (blueprint §2.2 / §2.4):
 * status, assignee_id, reporter_id, aprobado_por_id, team_id, priority,
 * summary, fecha_limite, start_date, dias_estimados, fecha_entrega, fecha_aprobacion,
 * software, entorno, impacto, labels
 *
 * Eventos: created, updated, deleted
 */
class ProyectoObserver
{
    /**
     * Campos que se rastrean en updated. Cualquier otro cambio se ignora.
     */
    private const TRACKED_FIELDS = [
        'status',
        'assignee_id',
        'reporter_id',
        'aprobado_por_id',
        'validado_por_id',
        'team_id',
        'priority',
        'summary',
        'fecha_limite',
        'start_date',
        'dias_estimados',
        'fecha_entrega',
        'fecha_aprobacion',
        'software',
        'entorno',
        'impacto',
        'labels',
    ];

    /** Campos cuyo valor es un user_id — se resuelven a nombre. */
    private const USER_ID_FIELDS = ['assignee_id', 'reporter_id', 'aprobado_por_id', 'validado_por_id'];

    /** Campos cuyo valor es un JSON array. */
    private const ARRAY_FIELDS = ['impacto', 'labels'];

    /** Campos cuyo valor es un team_id — se resuelven a nombre del equipo. */
    private const TEAM_ID_FIELDS = ['team_id'];

    private static array $userCache = [];
    private static array $teamCache = [];

    private function resolveUserName(?int $userId): ?string
    {
        if (!$userId) return null;
        if (!array_key_exists($userId, self::$userCache)) {
            self::$userCache[$userId] = User::find($userId)?->name ?? (string) $userId;
        }
        return self::$userCache[$userId];
    }

    private function resolveTeamName(?int $teamId): ?string
    {
        if (!$teamId) return null;
        if (!array_key_exists($teamId, self::$teamCache)) {
            self::$teamCache[$teamId] = GpTeam::find($teamId)?->name ?? (string) $teamId;
        }
        return self::$teamCache[$teamId];
    }

    // ─── Evento: tarea creada ─────────────────────────────────────────────

    public function created(Proyecto $proyecto): void
    {
        $userId   = Auth::id();
        $userName = $userId ? (Auth::user()?->name ?? null) : null;

        GpAuditLog::create([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'model_type' => 'Proyecto',
            'model_id'   => $proyecto->id,
            'model_key'  => $proyecto->key,
            'action'     => 'created',
            'old_values' => [],
            'new_values' => [
                'summary'  => $proyecto->summary,
                'status'   => $proyecto->status,
                'priority' => $proyecto->priority,
                'project'  => $proyecto->project,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);
    }

    // ─── Evento: tarea modificada ─────────────────────────────────────────

    public function updated(Proyecto $proyecto): void
    {
        $dirty = $proyecto->getDirty();

        // Filtrar solo campos rastreados
        $changedTracked = array_intersect_key($dirty, array_flip(self::TRACKED_FIELDS));

        if (empty($changedTracked)) {
            return;
        }

        // Batch-load todos los user_id y team_id presentes en los cambios
        // para evitar N+1 queries en actualizaciones masivas.
        $missingUserIds = [];
        $missingTeamIds = [];

        foreach ($changedTracked as $field => $newRaw) {
            $oldRaw = $proyecto->getOriginal($field);
            if (in_array($field, self::USER_ID_FIELDS, true)) {
                foreach ([$oldRaw, $newRaw] as $uid) {
                    if ($uid && !array_key_exists((int) $uid, self::$userCache)) {
                        $missingUserIds[] = (int) $uid;
                    }
                }
            } elseif (in_array($field, self::TEAM_ID_FIELDS, true)) {
                foreach ([$oldRaw, $newRaw] as $tid) {
                    if ($tid && !array_key_exists((int) $tid, self::$teamCache)) {
                        $missingTeamIds[] = (int) $tid;
                    }
                }
            }
        }

        if (!empty($missingUserIds)) {
            User::whereIn('id', array_unique($missingUserIds))
                ->get(['id', 'name'])
                ->each(fn ($u) => self::$userCache[$u->id] = $u->name);
        }

        if (!empty($missingTeamIds)) {
            GpTeam::whereIn('id', array_unique($missingTeamIds))
                ->get(['id', 'name'])
                ->each(fn ($t) => self::$teamCache[$t->id] = $t->name);
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changedTracked as $field => $newRaw) {
            $oldRaw = $proyecto->getOriginal($field);

            if (in_array($field, self::USER_ID_FIELDS, true)) {
                $oldValues[$field] = $this->resolveUserName($oldRaw ? (int) $oldRaw : null);
                $newValues[$field] = $this->resolveUserName($newRaw ? (int) $newRaw : null);
            } elseif (in_array($field, self::TEAM_ID_FIELDS, true)) {
                $oldValues[$field] = $this->resolveTeamName($oldRaw ? (int) $oldRaw : null);
                $newValues[$field] = $this->resolveTeamName($newRaw ? (int) $newRaw : null);
            } elseif (in_array($field, self::ARRAY_FIELDS, true)) {
                $oldValues[$field] = is_string($oldRaw) ? json_decode($oldRaw, true) : $oldRaw;
                $newValues[$field] = is_array($newRaw) ? $newRaw : (is_string($newRaw) ? json_decode($newRaw, true) : $newRaw);
            } else {
                $oldValues[$field] = $oldRaw;
                $newValues[$field] = $newRaw;
            }
        }

        $userId   = Auth::id();
        $userName = $userId ? (Auth::user()?->name ?? null) : null;

        GpAuditLog::create([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'model_type' => 'Proyecto',
            'model_id'   => $proyecto->id,
            'model_key'  => $proyecto->key,
            'action'     => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);
    }

    // ─── Evento: tarea eliminada (soft delete) ────────────────────────────

    public function deleted(Proyecto $proyecto): void
    {
        $userId   = Auth::id();
        $userName = $userId ? (Auth::user()?->name ?? null) : null;

        GpAuditLog::create([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'model_type' => 'Proyecto',
            'model_id'   => $proyecto->id,
            'model_key'  => $proyecto->key,
            'action'     => 'deleted',
            'old_values' => [
                'summary'    => $proyecto->summary,
                'status'     => $proyecto->status,
                'project'    => $proyecto->project,
                'issue_type' => $proyecto->issue_type,
                'priority'   => $proyecto->priority,
                'assignee'   => $this->resolveUserName($proyecto->assignee_id),
            ],
            'new_values' => [],
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);
    }
}
