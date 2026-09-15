<?php

namespace Modules\GestionProyectos\Services\DTOs;

use Carbon\Carbon;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Aplana un modelo Proyecto (con sus relaciones cargadas) en un array
 * para el frontend Vue — nomenclatura blueprint SCRUM PROJECT v1.0.
 */
class ProyectoDTO
{
    /**
     * Aplana una colección de modelos.
     *
     * @param  Proyecto[] $proyectos
     * @return array
     */
    public static function fromCollection(iterable $proyectos): array
    {
        return collect($proyectos)
            ->map(fn ($p) => self::fromModel($p))
            ->values()
            ->all();
    }

    /**
     * Aplana un único modelo Proyecto.
     */
    public static function fromModel(Proyecto $p): array
    {
        return [
            'id'          => (string) $p->id,
            'key'         => $p->key,
            'summary'     => $p->summary ?? '',
            'status'      => self::mapStatus($p->status),
            'priority'    => self::mapPriority($p->priority),
            'issue_type'  => ['id' => null, 'name' => $p->issue_type ?? 'Tarea'],
            'project'     => ['id' => null, 'name' => $p->project ?? ''],
            'assignee'    => self::mapUser($p->assignee),
            'creator'     => self::mapUser($p->creator),
            'reporter'    => self::mapUser($p->reporter),
            'aprobado_por'=> self::mapUser($p->aprobadoPor ?? null),
            'validado_por'=> self::mapUser($p->validadoPor ?? null),
            'labels'      => is_array($p->labels) ? $p->labels : [],
            'impacto'     => is_array($p->impacto) ? $p->impacto : [],
            // Fechas
            'created_at'           => self::formatDate($p->created_at),
            'created_at_raw'       => self::formatDateRaw($p->created_at), // ISO YYYY-MM-DD para filtros de rango
            'updated_at'           => self::formatDate($p->updated_at),
            'start_date'           => self::formatDateRaw($p->start_date),
            'fecha_limite'         => self::formatDateRaw($p->fecha_limite),
            'fecha_entrega'        => self::formatDateRaw($p->fecha_entrega),
            'fecha_aprobacion'     => self::formatDateRaw($p->fecha_aprobacion),
            'fecha_reprogramacion' => self::formatDateRaw($p->fecha_reprogramacion),
            // Campos SCRUM
            'software'             => $p->software,
            'entorno'              => $p->entorno,
            'dias_estimados'       => $p->dias_estimados,
            'solicitado_por'       => self::mapSolicitante($p),
            'categoria'            => $p->categoria,
            'description'          => $p->description,
            'custom_fields'        => self::mapCustomFields($p),
            // Equipo
            'team_id'              => $p->team_id,
            'team'                 => $p->team ? ['id' => $p->team->id, 'name' => $p->team->name] : null,
            // Sub tareas
            'sub_task_count'       => (int) ($p->sub_tareas_count ?? ($p->relationLoaded('subTareas') ? $p->subTareas->count() : 0)),
            // Subactividades (para la flecha de despliegue en la tabla)
            'sub_actividades_count' => (int) ($p->sub_actividades_count ?? ($p->relationLoaded('subActividades') ? $p->subActividades->count() : 0)),
            // Cambios en el audit log (excluye "created") — badge del botón timeline
            'audit_changes_count'  => (int) ($p->audit_changes_count ?? 0),
            // Entradas del historial de actividades (evidencia/comentarios) — badge del botón historial
            'activity_history_count' => (int) ($p->activity_history_count ?? ($p->relationLoaded('activityHistory') ? $p->activityHistory->count() : 0)),
            // Reprogramaciones
            'reprogramacion_root_key'  => $p->reprogramacion_root_key,
            'reprogramacion_n'         => $p->reprogramacion_n,
            'reprogramaciones_count'   => (int) ($p->reprogramaciones_count ?? ($p->relationLoaded('reprogramaciones') ? $p->reprogramaciones->count() : 0)),
        ];
    }

    // ─── Helpers privados ──────────────────────────────────────────────────

    protected static function mapStatus(?string $statusName): ?array
    {
        if ($statusName === null) {
            return null;
        }

        $categoryKey = match (true) {
            $statusName === 'Pendiente'                          => 'new',
            in_array($statusName, ['En Curso', 'En Pausa', 'En Revisión']) => 'indeterminate',
            $statusName === 'Finalizado'                         => 'done',
            default                                              => 'undefined',
        };

        $tailwind = match ($categoryKey) {
            'new'           => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800/40 dark:text-gray-300 dark:border-gray-700',
            'indeterminate' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800',
            'done'          => 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-800 dark:text-emerald-100 dark:border-emerald-800',
            default         => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-800',
        };

        return [
            'id'           => null,
            'name'         => $statusName,
            'category_key' => $categoryKey,
            'color_class'  => $tailwind,
        ];
    }

    protected static function mapPriority(?string $priorityName): ?array
    {
        if ($priorityName === null) {
            return null;
        }

        return [
            'id'       => null,
            'name'     => $priorityName,
            'icon_url' => null,
        ];
    }

    protected static function mapUser($user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'account_id'   => (string) $user->id,
            'display_name' => $user->name,
            'email'        => $user->email,
            // Usa accessor `getAvatarUrlAttribute` que envuelve con asset('storage/...')
            'avatar_url'   => $user->avatar_url ?? null,
        ];
    }

    /**
     * Resuelve "Solicitado Por" priorizando el nombre real almacenado en la columna
     * `solicitado_por` (importado de Jira o capturado a mano). Si coincide con el
     * reporter, reutiliza su avatar; si difiere, muestra solo el nombre. Sin string,
     * cae al reporter.
     */
    protected static function mapSolicitante($p): ?array
    {
        $nombre = is_string($p->solicitado_por) ? trim($p->solicitado_por) : '';

        if ($nombre === '') {
            return self::mapUser($p->reporter);
        }

        // Si el reporter coincide con el nombre, devolvemos su perfil completo (con avatar).
        if ($p->reporter && $p->reporter->name === $nombre) {
            return self::mapUser($p->reporter);
        }

        // Nombre real sin usuario asociado (típico de datos importados de Jira).
        return [
            'account_id'   => null,
            'display_name' => $nombre,
            'email'        => null,
            'avatar_url'   => null,
        ];
    }

    protected static function formatDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->locale('es')->isoFormat('D MMM YYYY');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Formato ISO para campos fecha editables (input type="date").
     */
    protected static function formatDateRaw($value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString(); // YYYY-MM-DD
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convierte los customFieldValues del modelo en un mapa enriquecido:
     * ['custom_3' => { value, field_id, name, type }]
     */
    protected static function mapCustomFields(Proyecto $p): array
    {
        if (!$p->relationLoaded('customFieldValues')) {
            return [];
        }

        $result = [];
        foreach ($p->customFieldValues as $cfv) {
            $fieldId = $cfv->custom_field_id;
            if ($cfv->relationLoaded('customField') && $cfv->customField) {
                // Caso normal: campo activo con metadatos completos
                $result['custom_' . $fieldId] = [
                    'value'    => $cfv->value,
                    'field_id' => $fieldId,
                    'name'     => $cfv->customField->name,
                    'type'     => $cfv->customField->type,
                ];
            } elseif (!$cfv->relationLoaded('customField')) {
                // Relación no cargada (no debería ocurrir con eager loading) — incluir con fallback
                $result['custom_' . $fieldId] = [
                    'value'    => $cfv->value,
                    'field_id' => $fieldId,
                    'name'     => 'Campo personalizado',
                    'type'     => 'text',
                ];
            }
            // Si la relación está cargada pero customField es null → FK huérfana (campo eliminado) — omitir
        }
        return $result;
    }
}
