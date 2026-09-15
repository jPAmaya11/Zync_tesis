<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\User\Models\User;

/**
 * Sub Tarea — Blueprint §2.5
 *
 * Hijo de una tarea principal (Proyecto). Solo 1 nivel.
 * Sin aprobación. Estado simple: Pendiente / Finalizado.
 */
class GpSubTarea extends Model
{
    use SoftDeletes;

    protected $table = 'gp_sub_tareas';

    protected $fillable = [
        'key',
        'parent_key',
        'summary',
        'description',
        'observacion',
        'assignee_id',
        'creator_id',
        'status',
        'priority',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'parent_key', 'key');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // ─── Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->key)) {
                // Usar MAX del sufijo numérico para ser resistente a soft-deletes y
                // race conditions (el llamador debe envolver en DB::transaction).
                $lastNum = static::withTrashed()
                    ->where('parent_key', $model->parent_key)
                    ->lockForUpdate()
                    ->max(DB::raw('CAST(SUBSTRING_INDEX(`key`, \'-\', -1) AS UNSIGNED)'));

                $model->key = $model->parent_key . '-' . (($lastNum ?? 0) + 1);
            }
        });

        // Gobierna el cambio de estado venga de donde venga (web o MCP). Es la
        // ÚNICA fuente de verdad: finalizar = solo Aprobador + evidencia; y
        // "Finalizado" es terminal. Las operaciones de sistema (seeders/CLI, sin
        // usuario autenticado) quedan libres.
        static::updating(function (self $sub) {
            // Operaciones de sistema (seeders/CLI sin usuario autenticado) quedan libres.
            if (!auth()->check()) {
                return;
            }

            // Estado terminal: una subtarea Finalizada es INMUTABLE en su CONTENIDO
            // (consistente con las Actividades). Cubre web y MCP: ni se reabre ni se editan
            // summary / descripción / prioridad / asignado de una tarea ya cerrada.
            // Solo se evalúan campos de contenido para no bloquear ciclo de vida (restore/deleted_at).
            if ($sub->getOriginal('status') === 'Finalizado') {
                $contentChanged = $sub->isDirty(['summary', 'description', 'observacion', 'assignee_id', 'status', 'priority']);
                if ($contentChanged) {
                    throw new GestionProyectosException('La tarea está finalizada (estado terminal) y no puede ser modificada.');
                }
                return; // cambios de ciclo de vida (p. ej. restore) no se bloquean
            }

            $user    = auth()->user();
            $project = optional($sub->padre)->project;

            // El Aprobador (canApprove SIN canWrite) participa del flujo pero no edita datos:
            // solo mueve el estado. Vive acá para que web y MCP apliquen lo mismo desde una
            // sola fuente, y RECHAZA en vez de filtrar en silencio —igual que ProyectoService
            // en actividades—, para que el llamador se entere de lo que no se guardó.
            $isApproveOnly = $project
                && !$user->hasRole(['admin', 'super-admin', 'super_admin'])
                && !$user->can('gestion-proyectos.admin')
                && !GpSpaceMember::canWrite($user->id, $project)
                && GpSpaceMember::canApprove($user->id, $project);

            if ($isApproveOnly
                && $sub->isDirty(['summary', 'description', 'observacion', 'assignee_id', 'priority'])) {
                throw new GestionProyectosException('El Aprobador solo puede cambiar el estado de la tarea.');
            }

            if (!$sub->isDirty('status')) {
                return;
            }

            $nuevo  = $sub->status;
            $previo = $sub->getOriginal('status');

            // Finalizar: solo el Aprobador del espacio + evidencia registrada.
            if ($nuevo === 'Finalizado' && $previo !== 'Finalizado') {
                $esAprobador = $user->hasRole(['admin', 'super-admin', 'super_admin'])
                    || $user->can('gestion-proyectos.admin')
                    || ($project && GpSpaceMember::canApprove($user->id, $project));

                if (!$esAprobador) {
                    throw new GestionProyectosException('Solo el Aprobador del espacio puede finalizar tareas.');
                }

                $tieneEvidencia = GpSubTareaHistorial::where('sub_tarea_id', $sub->id)
                    ->whereNotNull('comment')
                    ->where('comment', '!=', '')
                    ->exists();

                if (!$tieneEvidencia) {
                    throw new GestionProyectosException(
                        'Para finalizar la tarea se requiere registrar un comentario (evidencia).',
                        0,
                        ['requires' => 'sub_tarea_history']
                    );
                }
            }
        });

        // Force-delete: purgar el historial (inmutable, sin soft-delete) y sus adjuntos
        // en disco. El soft-delete NO toca historial ni archivos (es reversible).
        static::deleting(function (self $sub) {
            if (!$sub->isForceDeleting()) {
                return;
            }
            $historial = GpSubTareaHistorial::where('sub_tarea_id', $sub->id)->get();
            foreach ($historial as $h) {
                // Legacy (columna única) + nuevos adjuntos en el JSON `attachments`.
                if (!empty($h->attachment_path)) {
                    Storage::disk('local')->delete($h->attachment_path);
                }
                foreach ((is_array($h->attachments) ? $h->attachments : []) as $att) {
                    if (!empty($att['path'])) {
                        Storage::disk('local')->delete($att['path']);
                    }
                }
            }
            GpSubTareaHistorial::where('sub_tarea_id', $sub->id)->delete();
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public function toFrontend(): array
    {
        return [
            'id'            => $this->id,
            'key'           => $this->key,
            'parent_key'    => $this->parent_key,
            'summary'       => $this->summary,
            'description'   => $this->description,
            'observacion'   => $this->observacion,
            'status'        => $this->status,
            'priority'      => $this->priority,
            'assignee'      => $this->relationLoaded('assignee') && $this->assignee ? [
                'account_id'   => (string) $this->assignee->id,
                'display_name' => $this->assignee->name,
                'avatar_url'   => $this->assignee->avatar_url ?? null,
            ] : null,
            'creator'       => $this->relationLoaded('creator') && $this->creator ? [
                'account_id'   => (string) $this->creator->id,
                'display_name' => $this->creator->name,
                'avatar_url'   => $this->creator->avatar_url ?? null,
            ] : null,
            'created_at'    => $this->created_at?->locale('es')->isoFormat('D MMM YYYY'),
            'updated_at'    => $this->updated_at?->locale('es')->isoFormat('D MMM YYYY'),
        ];
    }
}
