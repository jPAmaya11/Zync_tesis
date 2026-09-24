<?php

namespace Modules\GestionProyectos\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpCustomFieldValue;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\User\Models\User;

class ProyectoService implements ProyectoServiceInterface
{
    // =========================================================================
    // Interfaz pública
    // =========================================================================

    /** @inheritDoc */
    public function getPaginated(array $filters, int $page = 1, int $perPage = 50): array
    {
        $query = Proyecto::with(['assignee', 'reporter', 'creator', 'aprobadoPor', 'validadoPor', 'customFieldValues.customField', 'team'])
                         ->withCount([
                             'subTareas',
                             'subActividades',
                             'reprogramaciones',
                             'auditLogs as audit_changes_count' => fn ($q) => $q->where('action', '!=', 'created'),
                             'activityHistory as activity_history_count',
                         ])
            ->whereNull('parent_key')
            ->whereNull('reprogramacion_root_key') // excluir versiones -Rn de la lista principal
            ->when(!empty($filters['project']), fn ($q) => $q->where('project', $filters['project']))
            ->when(!empty($filters['statuses']), fn ($q) => $q->whereIn('status', $filters['statuses']))
            ->when(!empty($filters['priorities']), fn ($q) => $q->whereIn('priority', $filters['priorities']))
            ->when(!empty($filters['issue_types']), fn ($q) => $q->whereIn('issue_type', $filters['issue_types']))
            ->when(!empty($filters['assignees']), function ($q) use ($filters) {
                // assignees puede ser array de user_id (int) o account_id (string "id")
                $ids = array_filter(array_map('intval', $filters['assignees']));
                if ($ids) {
                    $q->whereIn('assignee_id', $ids);
                }
            })
            ->when(!empty($filters['labels']), function ($q) use ($filters) {
                foreach ($filters['labels'] as $label) {
                    $q->whereJsonContains('labels', $label);
                }
            })
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = trim($filters['search']);
                // FULLTEXT MATCH AGAINST para terms de 3+ chars (gp_proyectos_fulltext_idx).
                // Para términos cortos o búsquedas de keys (ej. "ERP-0001") se usa LIKE.
                if (mb_strlen($term) >= 3) {
                    $words       = array_filter(explode(' ', $term));
                    $booleanTerm = implode(' ', array_map(fn ($w) => "+{$w}*", $words));
                    $q->where(function ($inner) use ($term, $booleanTerm) {
                        $inner->whereRaw('MATCH(summary, description) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                              ->orWhere('key', 'LIKE', "%{$term}%");
                    });
                } else {
                    $q->where(function ($inner) use ($term) {
                        $inner->where('summary', 'LIKE', "%{$term}%")
                              ->orWhere('key', 'LIKE', "%{$term}%")
                              ->orWhere('description', 'LIKE', "%{$term}%");
                    });
                }
            });

        // Ordenamiento
        $orderBy = $filters['order_by'] ?? 'created_at DESC';
        [$col, $dir] = $this->parseOrderBy($orderBy);
        $query->orderBy($col, $dir);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data'         => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    /** @inheritDoc */
    public function create(array $data): Proyecto
    {
        return DB::transaction(function () use ($data) {
            // M1 — Validar padre antes de crear subactividad.
            if (!empty($data['parent_key'])) {
                $padre = Proyecto::where('key', $data['parent_key'])->first();
                if (! $padre) {
                    throw new GestionProyectosException("La actividad padre no existe: {$data['parent_key']}.");
                }
                if (! empty($padre->parent_key)) {
                    throw new GestionProyectosException("No se puede crear una subactividad bajo otra subactividad ({$data['parent_key']}).");
                }
            }

            // Exclusividad responsable: una tarea se asigna a un equipo O a un usuario,
            // nunca a ambos. Si viene equipo, el usuario se descarta.
            if (!empty($data['team_id'])) {
                // No permitir asignar un equipo inactivo al crear la actividad.
                $team = \Modules\GestionProyectos\Models\GpTeam::find($data['team_id']);
                if ($team && ! $team->is_active) {
                    throw new GestionProyectosException('No se puede asignar un equipo inactivo a la actividad. Reactivá el equipo primero.');
                }
                $data['assignee_id'] = null;
            }

            // Resolver solicitado_por de forma centralizada si viene vacío
            $solicitadoPor = $data['solicitado_por'] ?? null;
            if (empty($solicitadoPor) && !empty($data['reporter_id'])) {
                $solicitadoPor = \Modules\User\Models\User::find($data['reporter_id'])?->name;
            }

            // El estado, si viene, debe ser del catálogo SCRUM (no se crean estados nuevos).
            if (isset($data['status'])) {
                $validStatuses = config('gestion-proyectos.statuses', []);
                if (! in_array($data['status'], $validStatuses, true)) {
                    throw new GestionProyectosException(
                        "Estado inválido: \"{$data['status']}\". Estados válidos: " . implode(', ', $validStatuses) . '.'
                    );
                }
            }

            $proyecto = Proyecto::create([
                'summary'        => $data['summary'],
                'status'         => $data['status']         ?? 'Pendiente',
                'priority'       => $data['priority']        ?? 'Media',
                'issue_type'     => $data['issue_type']      ?? 'Tarea',
                'project'        => $data['project_key']     ?? config('gestion-proyectos.projects.0', 'ERP'),
                'parent_key'     => $data['parent_key']      ?? null,
                'description'    => $data['description']     ?? null,
                'start_date'     => $data['start_date']      ?? null,
                // Persistir la Fecha Límite enviada (el hook la recalcula si hay días estimados;
                // para el mismo día / sin días, se respeta tal cual la eligió el usuario).
                'fecha_limite'   => $data['fecha_limite']    ?? null,
                'assignee_id'    => $data['assignee_id']     ?? null,
                'reporter_id'    => $data['reporter_id']     ?? null,
                'creator_id'     => $data['creator_id']      ?? null,
                'solicitado_por' => $solicitadoPor,
                'software'       => $data['software']        ?? null,
                'entorno'        => $data['entorno']         ?? null,
                'impacto'        => $data['impacto']         ?? null,
                'dias_estimados' => $data['dias_estimados']  ?? null,
                'team_id'        => $data['team_id']         ?? null,
                'fecha_entrega'  => $data['fecha_entrega']   ?? null,
                'fecha_aprobacion' => $data['fecha_aprobacion'] ?? null,
                'labels'         => $data['labels']          ?? [],
            ]);

            // Invalidar caché de etiquetas si se registraron nuevas
            if (!empty($data['labels'])) {
                $this->ensureLabels($proyecto->project, $data['labels']);
                Cache::forget('gp.labels');
            }

            // Alta al vuelo de la categoría en el catálogo del espacio.
            $this->ensureCategoria($proyecto->project, $data['categoria'] ?? null);

            // Persistir custom fields enviados en la creación (filtrando por pertenencia al proyecto)
            if (!empty($data['custom_fields']) && is_array($data['custom_fields'])) {
                $allowedFieldIds = \Modules\GestionProyectos\Models\GpCustomField::where('project_key', $proyecto->project)
                    ->pluck('id')
                    ->all();

                foreach ($data['custom_fields'] as $fieldId => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (!in_array((int) $fieldId, $allowedFieldIds, true)) {
                        continue; // Evita inyección cruzada de campos de otros proyectos
                    }
                    GpCustomFieldValue::updateOrCreate(
                        ['proyecto_id' => $proyecto->id, 'custom_field_id' => (int) $fieldId],
                        ['value' => (string) $value]
                    );
                }
            }

            return $proyecto;
        });
    }

    /** @inheritDoc */
    public function update(string $key, array $fields, ?User $actor = null): Proyecto
    {
        $actor    = $this->resolveActor($actor);
        $proyecto = Proyecto::where('key', $key)->firstOrFail();

        // 1. Validar autorización (Policy)
        if (!$actor->can('update', $proyecto)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("No tienes permiso para editar la tarea {$key}.");
        }

        // 1a. No permitir ASIGNAR un equipo inactivo. Si la actividad ya tenía ese equipo
        //     (no cambia), se conserva; solo se bloquea cambiarlo a uno inactivo.
        if (array_key_exists('team_id', $fields) && !empty($fields['team_id'])
            && (int) $fields['team_id'] !== (int) $proyecto->team_id) {
            $team = \Modules\GestionProyectos\Models\GpTeam::find($fields['team_id']);
            if ($team && ! $team->is_active) {
                throw new GestionProyectosException('No se puede asignar un equipo inactivo a la actividad. Reactivá el equipo primero.');
            }
        }

        // 1b. Aprobador (canApprove pero no canWrite): solo puede cambiar 'status' a estado crítico
        $isApproveOnly = !$actor->hasRole(['admin', 'super-admin', 'super_admin'])
            && !$actor->can('gestion-proyectos.admin')
            && !\Modules\GestionProyectos\Models\GpSpaceMember::canWrite($actor->id, $proyecto->project)
            && \Modules\GestionProyectos\Models\GpSpaceMember::canApprove($actor->id, $proyecto->project);

        if ($isApproveOnly) {
            $criticals   = config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']);
            $rollback    = config('gestion-proyectos.approver_rollback_statuses', []);
            $allowed     = array_values(array_unique(array_merge($criticals, $rollback)));
            $allowedKeys = ['status', 'project_key'];
            $extraKeys   = array_diff(array_keys($fields), $allowedKeys);
            if (!empty($extraKeys) || !isset($fields['status']) || !in_array($fields['status'], $allowed, true)) {
                throw new GestionProyectosException(
                    'El Aprobador solo puede cambiar el estado a: ' . implode(', ', $allowed) .
                    ' (aprobación o rechazo). No puede editar otros campos.'
                );
            }
        }

        // 1c. Desarrollador/Diseñador/Tester (rol "trabajador" restringido) en SU PROPIA
        // tarea asignada: solo pueden cambiar el campo 'status' (actualizar el estado de
        // su tarea). No editan ningún otro dato. El paso a un estado CRÍTICO (Finalizado/
        // Reprogramado) sigue bloqueado más abajo porque no están en APPROVER_ROLES.
        $isRestrictedWorkerOwnTask = !$actor->hasRole(['admin', 'super-admin', 'super_admin'])
            && !$actor->can('gestion-proyectos.admin')
            && !\Modules\GestionProyectos\Models\GpSpaceMember::canWrite($actor->id, $proyecto->project)
            && !\Modules\GestionProyectos\Models\GpSpaceMember::canApprove($actor->id, $proyecto->project)
            && \Modules\GestionProyectos\Models\GpSpaceMember::isRestrictedWorker($actor->id, $proyecto->project)
            && (int) $proyecto->assignee_id === (int) $actor->id;

        if ($isRestrictedWorkerOwnTask) {
            $extraKeys = array_diff(array_keys($fields), ['status']);
            if (!empty($extraKeys) || !isset($fields['status'])) {
                throw new GestionProyectosException(
                    'Solo puedes actualizar el estado de tus propias tareas asignadas. No puedes editar otros campos.'
                );
            }
        }

        // 2. Validar transiciones críticas (Blueprint §2.4)
        if (isset($fields['status'])) {
            // El estado debe pertenecer al CATÁLOGO SCRUM: no se pueden crear estados nuevos.
            // La web lo restringe con su dropdown, pero el MCP/API podrían enviar uno arbitrario
            // (p.ej. "revision" en vez de "En Revisión") y se persistía como estado fantasma.
            $validStatuses = config('gestion-proyectos.statuses', []);
            if (! in_array($fields['status'], $validStatuses, true)) {
                throw new GestionProyectosException(
                    "Estado inválido: \"{$fields['status']}\". Estados válidos: " . implode(', ', $validStatuses) . '.'
                );
            }

            // Estado terminal: una tarea finalizada es inmutable — no se puede cambiar el estado.
            $terminals = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
            if (in_array($proyecto->status, $terminals, true) && $fields['status'] !== $proyecto->status) {
                throw new GestionProyectosException(
                    "La tarea está en estado \"{$proyecto->status}\" (terminal) y no puede cambiar de estado."
                );
            }
        }

        // Estado terminal: bloquear edición de cualquier campo (no solo status).
        // Una tarea Finalizada es inmutable en todos sus campos. Hay DOS excepciones:
        //
        //  1. Por-espacio: si "Producción editable tras finalizar" está ON y la tarea está
        //     FINALIZADA (no Cancelada), se admite corregir SOLO fecha_aprobacion.
        //
        //  2. Siempre: `categoria`. Es metadato de CLASIFICACIÓN, no parte del resultado
        //     del trabajo ni de su rastro de aprobación: cambiarla no altera qué se hizo,
        //     quién lo aprobó ni cuándo. Bloquearla dejaría el campo inservible para todo
        //     el histórico —que en producción es la mayoría y está Finalizado—, y sin poder
        //     clasificar lo ya cerrado el campo no sirve para reportar. Aplica igual a
        //     actividades, subactividades (-Sn) y reprogramaciones (-Rn), porque las tres
        //     son filas de gp_proyectos y pasan por aquí.
        $terminals = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
        if (in_array($proyecto->status, $terminals, true)) {
            $editableKeys = ['status' => true, 'categoria' => true];
            if ($proyecto->status === 'Finalizado'
                && (bool) (\Modules\GestionProyectos\Models\GpProject::where('key', $proyecto->project)
                    ->value('produccion_editable_finalizado') ?? false)) {
                $editableKeys['fecha_aprobacion'] = true;
            }
            $modifiedOther = array_diff_key($fields, $editableKeys);
            if (!empty($modifiedOther)) {
                throw new GestionProyectosException(
                    "La tarea está en estado \"{$proyecto->status}\" (terminal) y no puede ser modificada."
                );
            }
        }

        if (isset($fields['status'])) {

            $criticals = config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']);
            if (in_array($fields['status'], $criticals, true)) {
                // Verificar rol de aprobación (aplica a TODOS los críticos, incl. Cancelado)
                if (!\Modules\GestionProyectos\Models\GpSpaceMember::canApprove($actor->id, $proyecto->project)) {
                    throw new GestionProyectosException("Solo el Administrador o Aprobador del espacio pueden mover a \"{$fields['status']}\".");
                }

                // Roll-up: no se puede Finalizar con hijos AÚN ACTIVOS (subactividades y tareas).
                // Los hijos en estado terminal (Finalizado / Cancelado) ya están cerrados y NO
                // bloquean el cierre del padre. whereNull('deleted_at') excluye soft-deleted.
                if ($fields['status'] === 'Finalizado') {
                    $cerrados = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
                    $pendSub = \Modules\GestionProyectos\Models\Proyecto::where('parent_key', $key)
                        ->whereNotIn('status', $cerrados)->whereNull('deleted_at')->pluck('key')->all();
                    $pendTar = \Modules\GestionProyectos\Models\GpSubTarea::where('parent_key', $key)
                        ->whereNotIn('status', $cerrados)->whereNull('deleted_at')->pluck('key')->all();
                    $pendientes = array_merge($pendSub, $pendTar);
                    if (!empty($pendientes)) {
                        throw new GestionProyectosException(
                            'No se puede Finalizar: hay subactividades/tareas sin completar (' . implode(', ', $pendientes) . ').'
                        );
                    }
                }

                $evidenceRequired = config('gestion-proyectos.evidence_required_statuses', ['Finalizado', 'Reprogramado', 'Cancelado']);
                if (in_array($fields['status'], $evidenceRequired, true)) {
                    // La evidencia debe ser ESPECÍFICA de esta transición: la entrada de
                    // historial de evidencia más reciente (con new_status) debe apuntar al
                    // estado destino. Así un comentario viejo (p.ej. de una reprogramación
                    // previa) NO habilita un Cancelado/Finalizado sin su propia evidencia.
                    $lastEvidence = \Modules\GestionProyectos\Models\GpActivityHistory::where('tarea_key', $key)
                        ->whereNotNull('new_status')
                        ->where('new_status', '!=', '')
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();
                    $hasValidHistory = $lastEvidence && $lastEvidence->new_status === $fields['status'];
                    if (!$hasValidHistory) {
                        throw new GestionProyectosException(
                            "Para pasar a \"{$fields['status']}\" se requiere registrar un comentario de evidencia en el Historial de Actividades.",
                            0,
                            ['requires' => 'activity_history']
                        );
                    }
                }

                // Reprogramado exige además la nueva fecha de vencimiento.
                // fecha_limite queda intacta como deadline original; fecha_reprogramacion
                // captura el nuevo objetivo. Display y SLA consultan COALESCE de ambas.
                if ($fields['status'] === 'Reprogramado' && empty($fields['fecha_reprogramacion'])) {
                    throw new GestionProyectosException(
                        'Para reprogramar la tarea debes indicar la nueva fecha de vencimiento.',
                        0,
                        ['requires' => 'fecha_reprogramacion']
                    );
                }
            }
        }

        // 3. Exclusividad responsable equipo ↔ usuario. Asignar uno limpia el otro.
        if (array_key_exists('team_id', $fields) && !empty($fields['team_id'])) {
            $fields['assignee_id'] = null;
        } elseif (array_key_exists('assignee_id', $fields) && !empty($fields['assignee_id'])) {
            $fields['team_id'] = null;
        }

        $proyecto->update($fields);

        // Limpiar caché de etiquetas si cambiaron + crear al vuelo las nuevas del espacio
        if (isset($fields['labels'])) {
            $this->ensureLabels($proyecto->project, $fields['labels']);
            Cache::forget('gp.labels');
        }

        // Alta al vuelo de la categoría si es una que aún no existía en el espacio.
        if (array_key_exists('categoria', $fields)) {
            $this->ensureCategoria($proyecto->project, $fields['categoria']);
        }

        return $proyecto->fresh(['assignee', 'reporter', 'creator']);
    }

    /**
     * Crea "al vuelo" en el catálogo del espacio las etiquetas (por nombre) que aún
     * no existan. Idempotente: las existentes se omiten (unique project_key+name).
     */
    private function ensureLabels(string $projectKey, array $names): void
    {
        foreach (array_unique(array_filter(array_map('trim', $names))) as $name) {
            \Modules\GestionProyectos\Models\GpLabel::firstOrCreate(
                ['project_key' => $projectKey, 'name' => $name]
            );
        }
    }

    /**
     * Da de alta "al vuelo" la categoría en el catálogo del espacio, si aún no existía.
     *
     * Misma filosofía que las etiquetas: la lista NO se mantiene a mano. La primera vez
     * alguien la escribe y desde entonces queda en el desplegable de ESE espacio.
     * Idempotente por el unique(project_key, name); un valor vacío no crea nada.
     */
    private function ensureCategoria(string $projectKey, ?string $nombre): void
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return;
        }

        \Modules\GestionProyectos\Models\GpCategoria::firstOrCreate(
            ['project_key' => $projectKey, 'name' => $nombre]
        );
    }

    /** @inheritDoc */
    public function delete(string $key, ?User $actor = null): void
    {
        $actor    = $this->resolveActor($actor);
        $proyecto = Proyecto::where('key', $key)->firstOrFail();

        if (!$actor->can('eliminar', $proyecto)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("No tienes permiso para eliminar la tarea {$key}.");
        }

        // Transacción: la cascada del modelo (soft-delete de hijos) debe ser atómica.
        // Sin esto, un fallo a mitad del cascade deja subactividades/tareas huérfanas.
        DB::transaction(fn () => $proyecto->delete());
    }

    /**
     * Reprograma una actividad o subactividad creando una versión sucesora (-R{n}).
     *
     * Flujo:
     *  1. Valida permisos (aprobador+) y que la actividad acepte reprogramación.
     *  2. Registra el motivo en el historial de actividades del original.
     *  3. Transiciona el original a estado "Reprogramado".
     *  4. Crea la versión sucesora (-R{n}) con sus propios datos; el resto se hereda del padre.
     *
     * @param string $key    Key de la actividad/subactividad a reprogramar.
     * @param string $motivo Comentario obligatorio (por qué se reprograma) — evidencia.
     * @param array  $plan   Datos propios del sucesor:
     *   summary, description, status, priority, assignee_id, start_date, fecha_limite, dias_estimados.
     *   Lo NO incluido (issue_type, reporter, labels, impacto, software, entorno, team) se hereda del padre.
     * @param ?User  $actor  Usuario que ejecuta la acción.
     */
    public function reprogramar(
        string $key,
        string $motivo,
        array  $plan,
        ?User  $actor = null
    ): Proyecto {
        $actor    = $this->resolveActor($actor);
        $original = Proyecto::where('key', $key)->firstOrFail();

        // Permiso: solo aprobadores del espacio pueden reprogramar.
        if (!\Modules\GestionProyectos\Models\GpSpaceMember::canApprove($actor->id, $original->project)) {
            throw new GestionProyectosException('Solo el Aprobador o Administrador del espacio puede reprogramar una actividad.');
        }

        // No reprogramar una versión -Rn (reprograma siempre la raíz o subactividad directa).
        if ($original->reprogramacion_root_key) {
            throw new GestionProyectosException('No se puede reprogramar una versión reprogramada. Reprograma la actividad raíz o el último plan activo.');
        }

        // No reprogramar actividades ya finalizadas o canceladas.
        $terminals = config('gestion-proyectos.terminal_statuses', ['Finalizado']);
        if (in_array($original->status, array_merge($terminals, ['Cancelado']), true)) {
            throw new GestionProyectosException("La actividad está en estado \"{$original->status}\" y no puede reprogramarse.");
        }

        // Switch por-espacio "Bloquear fechas anteriores": si el espacio lo tiene apagado, la
        // fecha de inicio queda libre y NO se aplica el piso del inicio del padre.
        $bloquearFechas = (bool) (\Modules\GestionProyectos\Models\GpProject::where('key', $original->project)
            ->value('validar_fechas_inicio') ?? true);

        // Si es una SUBACTIVIDAD, la nueva fecha de inicio no puede ser anterior al inicio
        // de su actividad padre (la sucesora -Rn no hereda parent_key, así que el hook de
        // fechas no la cubre: se valida aquí explícitamente).
        if ($bloquearFechas && $original->parent_key && !empty($plan['start_date'])) {
            $padre = Proyecto::where('key', $original->parent_key)->first();
            if ($padre && $padre->start_date
                && \Carbon\Carbon::parse($plan['start_date'])->lt(\Carbon\Carbon::parse($padre->start_date))) {
                throw new GestionProyectosException(
                    'La fecha de inicio de la reprogramación no puede ser anterior al inicio de su actividad padre ('
                    . \Carbon\Carbon::parse($padre->start_date)->toDateString() . ').'
                );
            }
        }

        // El sucesor nace solo en Pendiente o En Revisión (plan que arranca).
        $statusSucesor = in_array(($plan['status'] ?? 'Pendiente'), ['Pendiente', 'En Revisión'], true)
            ? $plan['status']
            : 'Pendiente';

        $rootKey = $key;

        return DB::transaction(function () use ($original, $rootKey, $motivo, $plan, $statusSucesor, $actor) {
            // 1. Registrar motivo en el historial del original (evidencia de la transición).
            \Modules\GestionProyectos\Models\GpActivityHistory::create([
                'tarea_key'   => $original->key,
                'user_id'     => $actor->id,
                'comment'     => "[Reprogramación] {$motivo}",
                'new_status'  => 'Reprogramado',
            ]);

            // 3. Crear la versión sucesora: campos propios del plan + herencia del padre.
            $nuevaInicio = $plan['start_date'];
            $nuevaLimite = $plan['fecha_limite'];

            // 2. Transicionar el original a Reprogramado y registrar el nuevo objetivo en
            //    fecha_reprogramacion. fecha_limite queda intacta como deadline original;
            //    el SLA y los KPIs usan COALESCE(fecha_reprogramacion, fecha_limite).
            $original->update([
                'status'               => 'Reprogramado',
                'fecha_reprogramacion' => $nuevaLimite,
            ]);
            $diff = $plan['dias_estimados']
                ?? (int) \Carbon\Carbon::parse($nuevaInicio)->diffInDays(\Carbon\Carbon::parse($nuevaLimite), false);

            $sucesor = Proyecto::create([
                // Heredados del padre (no editables en el modal)
                'project'                 => $original->project,
                'issue_type'              => $original->issue_type,
                'reporter_id'             => $original->reporter_id,
                'labels'                  => $original->labels,
                'impacto'                 => $original->impacto,
                'software'                => $original->software,
                'entorno'                 => $original->entorno,
                'team_id'                 => $original->team_id,
                // Propios del sucesor (definidos en el modal de reprogramación)
                'summary'                 => $plan['summary'],
                'description'             => $plan['description'] ?? null,
                'status'                  => $statusSucesor,
                'priority'                => $plan['priority'] ?? $original->priority,
                'assignee_id'             => $plan['assignee_id'] ?? null,
                'start_date'              => $nuevaInicio,
                'fecha_limite'            => $nuevaLimite,
                'dias_estimados'          => $diff >= 0 ? $diff : null,
                // Metadatos de reprogramación
                'creator_id'              => $actor->id,
                'reprogramacion_root_key' => $rootKey,
            ]);

            return $sucesor->fresh(['assignee', 'reporter', 'creator']);
        });
    }

    /**
     * Devuelve las versiones de reprogramación (-R1, -R2, …) de una actividad raíz.
     */
    public function getReprogramaciones(string $rootKey): array
    {
        $versiones = Proyecto::with(['assignee', 'creator', 'aprobadoPor', 'validadoPor'])
            ->withCount([
                'auditLogs as audit_changes_count' => fn ($q) => $q->where('action', '!=', 'created'),
                'activityHistory as activity_history_count',
                // Una -Rn puede tener subactividades (y éstas, tareas): contadores para sus badges.
                'subActividades as sub_actividades_count',
                'subTareas as sub_tareas_count',
            ])
            ->where('reprogramacion_root_key', $rootKey)
            ->orderBy('reprogramacion_n')
            ->get();

        return \Modules\GestionProyectos\Services\DTOs\ProyectoDTO::fromCollection($versiones);
    }

    /**
     * KPIs de un espacio SCRUM (solo el espacio indicado).
     *
     * Unidad de análisis: ACTIVIDADES RAÍZ (top-level), excluye subactividades,
     * tareas y versiones de reprogramación (-Rn). El deadline para "a tiempo"
     * es el EFECTIVO: la fecha límite de la última versión -Rn si existe, si no
     * la propia. La fecha de cierre es fecha_aprobacion (fallback updated_at).
     *
     * @return array{plazos: array, reprog: array, rankings: array, estados: array}
     */
    public function getEspacioKpis(string $projectKey): array
    {
        $roots = Proyecto::where('project', $projectKey)
            ->whereNull('parent_key')
            ->whereNull('reprogramacion_root_key')
            ->whereNull('deleted_at')
            ->get(['key', 'summary', 'status', 'start_date', 'fecha_limite', 'fecha_reprogramacion', 'fecha_aprobacion', 'updated_at']);

        // Versiones -Rn por raíz: para deadline efectivo (última) y conteo de reprogramaciones.
        $versionesPorRoot = Proyecto::where('project', $projectKey)
            ->whereNotNull('reprogramacion_root_key')
            ->whereNull('deleted_at')
            ->orderBy('reprogramacion_n')
            ->get(['reprogramacion_root_key', 'fecha_limite'])
            ->groupBy('reprogramacion_root_key');

        $deadlineEfectivo = $versionesPorRoot->map(fn ($g) => $g->last()->fecha_limite); // last() = mayor n
        $reprogCount      = $versionesPorRoot->map(fn ($g) => $g->count());

        $terminales = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
        $hoy = \Carbon\Carbon::now()->startOfDay();

        $total           = $roots->count();
        $finalizadas     = 0;
        $canceladas      = 0;
        $statusDist      = [];
        $onTime          = 0;
        $late            = 0;
        $vencidasActivas = 0;
        $desvSum = 0; $desvCount = 0;
        $leadSum = 0; $leadCount = 0;

        $rankReprog = []; // {key, summary, value=#reprogramaciones}
        $rankVencidos = []; // {key, summary, value=días vencidos}
        $rankTerminados = []; // {key, summary, value=lead días}

        foreach ($roots as $r) {
            $statusDist[$r->status] = ($statusDist[$r->status] ?? 0) + 1;
            if ($r->status === 'Finalizado') $finalizadas++;
            if ($r->status === 'Cancelado')  $canceladas++;

            // Deadline efectivo: última versión -Rn, si no, el objetivo reprogramado del
            // propio root (camino MCP/A que reprograma sin crear -Rn), si no, el límite original.
            $effDeadline = $deadlineEfectivo[$r->key] ?? $r->fecha_reprogramacion ?? $r->fecha_limite;

            // Ranking reprogramados.
            $nReprog = (int) ($reprogCount[$r->key] ?? 0);
            if ($nReprog > 0) {
                $rankReprog[] = ['key' => $r->key, 'summary' => $r->summary ?? '', 'value' => $nReprog];
            }

            // Vencidas activas (+ ranking de vencidos): no terminal y deadline efectivo ya pasado.
            $esActiva = ! in_array($r->status, $terminales, true);
            if ($esActiva && $effDeadline) {
                $dlD = \Carbon\Carbon::parse($effDeadline)->startOfDay();
                if ($dlD->lt($hoy)) {
                    $vencidasActivas++;
                    $rankVencidos[] = ['key' => $r->key, 'summary' => $r->summary ?? '', 'value' => (int) abs($dlD->diffInDays($hoy))];
                }
            }

            // Cumplimiento / desviación / lead time + ranking terminados: solo Finalizadas.
            if ($r->status === 'Finalizado') {
                $cierre = $r->fecha_aprobacion ?? $r->updated_at;

                if ($cierre && $effDeadline) {
                    $cierreD = \Carbon\Carbon::parse($cierre)->startOfDay();
                    $dlD     = \Carbon\Carbon::parse($effDeadline)->startOfDay();
                    if ($cierreD->lte($dlD)) {
                        $onTime++;
                    } else {
                        $late++;
                        $desvSum += abs($dlD->diffInDays($cierreD));
                        $desvCount++;
                    }
                }
                if ($cierre && $r->start_date) {
                    $lead = (int) abs(\Carbon\Carbon::parse($r->start_date)->startOfDay()
                        ->diffInDays(\Carbon\Carbon::parse($cierre)->startOfDay()));
                    $leadSum += $lead;
                    $leadCount++;
                    $rankTerminados[] = ['key' => $r->key, 'summary' => $r->summary ?? '', 'value' => $lead];
                }
            }
        }

        // Top 5 de cada ranking (desc por value).
        $top5 = function (array $rows) {
            usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);
            return array_slice($rows, 0, 5);
        };

        $cerradas      = $finalizadas + $canceladas;
        $activas       = $total - $cerradas;
        $finConCierre  = $onTime + $late;
        $reprogramadas = count($rankReprog);

        return [
            'plazos' => [
                'on_time'              => $onTime,
                'late'                 => $late,
                'on_time_pct'          => $finConCierre ? (int) round($onTime / $finConCierre * 100) : 0,
                'vencidas_activas'     => $vencidasActivas,
                'desviacion_prom_dias' => $desvCount ? (int) round($desvSum / $desvCount) : 0,
                'lead_time_dias'       => $leadCount ? (int) round($leadSum / $leadCount) : 0,
            ],
            'reprog' => [
                'tasa_pct'  => $total ? (int) round($reprogramadas / $total * 100) : 0,
                'promedio'  => $reprogramadas ? round(array_sum(array_column($rankReprog, 'value')) / $reprogramadas, 1) : 0,
            ],
            'rankings' => [
                'reprogramados' => $top5($rankReprog),
                'vencidos'      => $top5($rankVencidos),
                'terminados'    => $top5($rankTerminados),
            ],
            'estados' => [
                'distribucion'          => $statusDist,
                'total'                 => $total,
                'finalizadas'           => $finalizadas,
                'canceladas'            => $canceladas,
                'activas'               => $activas,
                'cerradas'              => $cerradas,
                'tasa_finalizacion_pct' => $total ? (int) round($finalizadas / $total * 100) : 0,
                'tasa_cancelacion_pct'  => $total ? (int) round($canceladas / $total * 100) : 0,
            ],
        ];
    }

    /**
     * Resuelve el actor de la operación: prioriza el provisto explícitamente,
     * cae al usuario autenticado del request, falla si no hay ninguno.
     */
    private function resolveActor(?User $actor): User
    {
        $actor ??= auth()->user();
        if (!$actor) {
            throw new GestionProyectosException(
                'No hay actor disponible: el método requiere un User explícito cuando no hay sesión HTTP activa.'
            );
        }
        return $actor;
    }

    /** @inheritDoc */
    public function bulkUpdate(array $keys, array $fields, ?array $labelIds = null, ?User $actor = null): array
    {
        $actor     = $this->resolveActor($actor);
        $succeeded = [];
        $failed    = [];

        // Los cambios de estado crítico requieren evidencia adjunta individual por tarea
        // y deben procesarse uno a uno para respetar el flujo de aprobación.
        unset($fields['status']);

        // Resolver etiquetas UNA SOLA VEZ antes del loop (evita N queries idénticas).
        $labelNames = null;
        if ($labelIds !== null) {
            $labelNames = \Modules\GestionProyectos\Models\GpLabel::whereIn('id', $labelIds)
                ->pluck('name')
                ->all();
            Cache::forget('gp.labels');
        }

        // Suprime correos individuales para emitir un digest único por destinatario al cerrar.
        \Modules\GestionProyectos\Support\BulkContext::enter();
        try {
            foreach ($keys as $key) {
                try {
                    $proyecto = $this->update($key, $fields, $actor);

                    if ($labelNames !== null) {
                        $proyecto->update(['labels' => $labelNames]);
                    }

                    $succeeded[] = $key;
                } catch (\Throwable $e) {
                    $failed[] = ['key' => $key, 'error' => $e->getMessage()];
                }
            }

            $this->emitBulkDigest($actor);
        } finally {
            \Modules\GestionProyectos\Support\BulkContext::leave();
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Toma los cambios registrados durante el bloque BulkContext, agrupa por
     * destinatario y emite 1 correo digest a cada uno (acordado: bulk → 1 mail por user).
     */
    private function emitBulkDigest(User $actor): void
    {
        $entries = \Modules\GestionProyectos\Support\BulkContext::flush();
        if ($entries === []) {
            return;
        }

        // Pivot: userId => [items[]]
        $byUser = [];
        foreach ($entries as $entry) {
            foreach ($entry['recipients'] as $uid) {
                $byUser[$uid] ??= [];
                $byUser[$uid][] = [
                    'key'     => $entry['key'],
                    'summary' => $entry['summary'],
                    'changes' => $entry['changes'],
                ];
            }
        }

        if ($byUser === []) {
            return;
        }

        $userEmails = \Modules\User\Models\User::query()
            ->whereIn('id', array_keys($byUser))
            ->pluck('email', 'id')
            ->all();

        $actorName  = $actor->name ?? 'Sistema';
        $dispatcher = app(\Modules\GestionProyectos\Services\Mail\MailDispatcher::class);

        foreach ($byUser as $uid => $items) {
            $email = $userEmails[$uid] ?? null;
            if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $mail = new \Modules\GestionProyectos\Mail\ScrumBulkDigestMail($actorName, $items);
            $dispatcher->sendMailable(
                $mail,
                [$email],
                meta: [
                    'trigger_type' => 'scrum.bulk.digest',
                    'model_type'   => null,
                    'model_id'     => null,
                ],
            );
        }
    }

    /** @inheritDoc */
    public function bulkDelete(array $keys, ?User $actor = null): array
    {
        $actor = $this->resolveActor($actor);

        // Log estructurado de la intención antes de ejecutar: permite auditar
        // qué keys se solicitaron incluso si alguna falla individualmente.
        GpAuditLog::create([
            'user_id'    => $actor->id,
            'user_name'  => $actor->name,
            'model_type' => 'Proyecto',
            'model_id'   => null,
            'model_key'  => null,
            'action'     => 'bulk_deleted',
            'old_values' => ['keys_solicitadas' => $keys, 'total' => count($keys)],
            'new_values' => [],
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);

        $succeeded = [];
        $failed    = [];

        foreach ($keys as $key) {
            try {
                $this->delete($key, $actor);
                $succeeded[] = $key;
            } catch (\Throwable $e) {
                $failed[] = ['key' => $key, 'error' => $e->getMessage()];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Usuarios asignables de un conjunto de espacios (para MCP sin project_key y no admin).
     * Devuelve la unión de miembros de todos los espacios dados, filtrados por permiso + activo.
     *
     * @param string[] $projectKeys
     */
    public function getAssignableUsersForSpaces(array $projectKeys, string $query = ''): array
    {
        if (empty($projectKeys)) {
            return [];
        }

        $users = User::active()
            ->permission('gestion-proyectos.miembro')
            ->whereIn('id', function ($sub) use ($projectKeys) {
                $sub->select('user_id')
                    ->from('gp_space_members')
                    ->where('suspended', false)
                    ->whereIn('project_key', $projectKeys);
            })
            ->when($query !== '', fn ($q) => $q->where(function ($inner) use ($query) {
                $inner->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
            }))
            ->orderBy('name')
            // Tope de seguridad del autocompletado. Cualquier usuario es alcanzable
            // escribiendo su nombre/email (filtro server-side); el tope solo acota el
            // listado inicial sin búsqueda. Solución plena para orgs muy grandes: paginar.
            ->limit(200)
            ->get(['id', 'name', 'email', 'avatar']);

        return $users->map(fn ($u) => [
            'account_id'   => (string) $u->id,
            'display_name' => $u->name,
            'email'        => $u->email,
            'avatar_url'   => $u->avatar_url ?? null,
        ])->values()->all();
    }

    /** @inheritDoc */
    public function getAssignableUsers(string $projectKey = '', string $query = ''): array
    {
        // Solo usuarios ACTIVOS y que tengan el permiso "gestion-proyectos.miembro".
        // El permiso "ver" NO alcanza: para ser asignado hay que poder ser miembro.
        // (Los admin lo heredan porque el rol admin sincroniza todos los permisos.)
        $users = User::active()
            ->permission('gestion-proyectos.miembro')
            ->when($projectKey !== '', function ($q) use ($projectKey) {
                // Alcanzables en el espacio = equipo real del espacio: miembros registrados
                // (gp_space_members) + el PROPIETARIO (owner_id, aunque no tenga fila de
                // membresía). NO se incluyen los admins globales por su rol: un admin global
                // que no sea dueño ni miembro de este espacio no debe aparecer en su dropdown
                // (decisión de producto). Si necesita aparecer, se le añade como miembro.
                $ownerId = \Modules\GestionProyectos\Models\GpProject::where('key', $projectKey)->value('owner_id');
                $q->where(function ($w) use ($projectKey, $ownerId) {
                    $w->whereIn('id', function ($sub) use ($projectKey) {
                        $sub->select('user_id')
                            ->from('gp_space_members')
                            ->where('suspended', false)
                            ->where('project_key', $projectKey);
                    });
                    if ($ownerId) {
                        $w->orWhere('id', (int) $ownerId);
                    }
                });
            })
            ->when($query !== '', fn ($q) => $q->where(function ($inner) use ($query) {
                $inner->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
            }))
            ->orderBy('name')
            // Tope de seguridad del autocompletado. Cualquier usuario es alcanzable
            // escribiendo su nombre/email (filtro server-side); el tope solo acota el
            // listado inicial sin búsqueda. Solución plena para orgs muy grandes: paginar.
            ->limit(200)
            ->get(['id', 'name', 'email', 'avatar']);

        return $users->map(fn ($u) => [
            'account_id'   => (string) $u->id,
            'display_name' => $u->name,
            'email'        => $u->email,
            // Usa accessor `getAvatarUrlAttribute` que envuelve con asset('storage/...')
            'avatar_url'   => $u->avatar_url ?? null,
        ])->values()->all();
    }

    /**
     * Usuarios elegibles para ser miembros de un espacio:
     * deben tener gestion-proyectos.ver + gestion-proyectos.miembro, o ser admin global.
     */
    public function getSpaceMemberCandidates(): array
    {
        $users = User::permission(['gestion-proyectos.miembro', 'gestion-proyectos.admin'])
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar']);

        return $users->map(fn ($u) => [
            'account_id'   => (string) $u->id,
            'display_name' => $u->name,
            'email'        => $u->email,
            'avatar_url'   => $u->avatar_url ?? null,
        ])->values()->all();
    }

    /** @inheritDoc */
    public function getIssueTypes(): array
    {
        return collect(config('gestion-proyectos.issue_types', []))
            ->map(fn ($name, $i) => ['id' => (string)($i + 1), 'name' => $name, 'iconUrl' => null])
            ->values()
            ->all();
    }

    /** @inheritDoc */
    public function getProjects(): array
    {
        return Cache::remember('gp.projects', 300, function () {
            return GpProject::active()
                ->ordered()
                ->with(['owner', 'assignee', 'spaceCategory', 'labels', 'teams'])
                ->get()
                ->map(fn (GpProject $p) => $p->toFrontend())
                ->values()
                ->all();
        });
    }

    /** @inheritDoc */
    public function getStatuses(): array
    {
        return config('gestion-proyectos.statuses', []);
    }

    /** @inheritDoc */
    public function getPriorities(): array
    {
        return collect(config('gestion-proyectos.priorities', []))
            ->map(fn ($name, $i) => [
                'id'      => (string)($i + 1),
                'name'    => $name,
                'iconUrl' => null,
            ])
            ->values()
            ->all();
    }

    /** @inheritDoc */
    public function getLabels(): array
    {
        // Obtener labels únicas de la base de datos de manera eficiente
        return Cache::remember('gp.labels', 3600, function () {
            if (DB::getDriverName() === 'mysql') {
                try {
                    $results = DB::select('SELECT DISTINCT JSON_UNQUOTE(label) as name FROM gp_proyectos, JSON_TABLE(labels, "$[*]" COLUMNS (label VARCHAR(255) PATH "$")) as jt WHERE labels IS NOT NULL AND JSON_VALID(labels)');
                    return collect($results)
                        ->pluck('name')
                        ->filter(fn ($l) => is_string($l) && $l !== '')
                        ->sort()
                        ->values()
                        ->all();
                } catch (\Throwable $e) {
                    // Fallback a PHP si algo falla o la versión de MySQL no lo soporta
                }
            }

            // Fallback para SQLite/Testing — chunked para evitar OOM con tablas grandes
            $labels = collect();
            Proyecto::whereNotNull('labels')
                ->select('labels')
                ->chunk(500, function ($chunk) use (&$labels) {
                    $labels = $labels->merge(
                        $chunk->flatMap(fn ($r) => is_array($r->labels) ? $r->labels : [])
                    );
                });

            return $labels
                ->filter(fn ($l) => is_string($l) && $l !== '')
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }

    /** @inheritDoc */
    public function getTransitions(string $key): array
    {
        // En base de datos local los "estados" disponibles son libres.
        // Devolvemos todos los estados de config como transiciones posibles.
        return collect(config('gestion-proyectos.statuses', []))
            ->map(fn ($status, $i) => [
                'id'   => (string) ($i + 1),
                'name' => 'Mover a ' . $status,
                'to'   => $status,
            ])
            ->values()
            ->all();
    }

    // =========================================================================
    // Helpers internos
    // =========================================================================

    /**
     * Parsea "campo DIR" → [$columna, $dirección].
     * Soporta: "created_at DESC", "summary ASC", etc.
     */
    protected function parseOrderBy(string $orderBy): array
    {
        $parts = explode(' ', trim($orderBy), 2);
        $col   = $parts[0] ?? 'created_at';
        $dir   = strtoupper($parts[1] ?? 'DESC');

        // Whitelist de columnas permitidas
        $allowed = ['created_at', 'updated_at', 'summary', 'status', 'priority', 'fecha_limite', 'start_date', 'key'];
        if (!in_array($col, $allowed, true)) {
            $col = 'created_at';
        }

        $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'DESC';

        return [$col, $dir];
    }
}
