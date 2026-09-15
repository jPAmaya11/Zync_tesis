<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpCustomFieldValue;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\User\Models\User;
class Proyecto extends Model
{
    use SoftDeletes;

    protected $table = 'gp_proyectos';

    protected $fillable = [
        'key',
        'summary',
        'status',
        'assignee_id',
        'reporter_id',
        'creator_id',
        'priority',
        'labels',
        'issue_type',
        'project',
        'parent_key',
        'description',
        'start_date',
        'solicitado_por',
        // Categoría de la actividad. Catálogo por espacio en gp_categorias, que crece
        // solo (igual que las etiquetas); aquí se guarda el NOMBRE, no el id.
        'categoria',
        // Nuevos campos SCRUM PROJECT
        'team_id',
        'software',
        'entorno',
        'impacto',
        'dias_estimados',
        'fecha_limite',
        'fecha_entrega',
        'fecha_aprobacion',
        'aprobado_por_id',
        'validado_por_id',
        'fecha_reprogramacion',
        'reprogramacion_root_key',
        'reprogramacion_n',
    ];

    protected $casts = [
        'labels'                => 'array',
        'impacto'               => 'array',
        'start_date'            => 'date',
        'fecha_limite'          => 'date',
        'fecha_entrega'         => 'date',
        'fecha_aprobacion'      => 'date',
        'fecha_reprogramacion'  => 'date',
        'reprogramacion_n'      => 'integer',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(GpCustomFieldValue::class, 'proyecto_id');
    }

    public function activityHistory(): HasMany
    {
        return $this->hasMany(GpActivityHistory::class, 'tarea_key', 'key')
                    ->orderByDesc('created_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(GpAuditLog::class, 'model_key', 'key');
    }

    public function subTareas(): HasMany
    {
        return $this->hasMany(GpSubTarea::class, 'parent_key', 'key')
                    ->orderBy('created_at');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(GpTeam::class, 'team_id');
    }

    /** Actividad padre (si esta fila es una Subactividad). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_key', 'key');
    }

    /** Subactividades hijas (Proyectos anidados por parent_key). */
    public function subActividades(): HasMany
    {
        return $this->hasMany(self::class, 'parent_key', 'key')->orderBy('created_at');
    }

    /** Versiones de reprogramación de esta actividad raíz (-R1, -R2, …). */
    public function reprogramaciones(): HasMany
    {
        return $this->hasMany(self::class, 'reprogramacion_root_key', 'key')
                    ->orderBy('reprogramacion_n');
    }

    /** Actividad raíz de esta versión de reprogramación. */
    public function reprogramacionRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reprogramacion_root_key', 'key');
    }

    /** Scope: sólo actividades top-level (excluye subactividades y versiones reprogramadas). */
    public function scopeActividades($query)
    {
        return $query->whereNull('parent_key')->whereNull('reprogramacion_root_key');
    }

    // ─── Boot ──────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Autogenerar key al crear
        static::creating(function (self $model) {
            if (empty($model->key)) {
                if (!empty($model->reprogramacion_root_key)) {
                    // Versión reprogramada: {ROOT_KEY}-R{n}, secuencia global bajo la misma raíz.
                    // Bloquear la FILA del root serializa los inserts concurrentes de -Rn:
                    // el lockForUpdate sobre un MAX agregado no impide el phantom insert.
                    $rootKey = $model->reprogramacion_root_key;
                    static::where('key', $rootKey)->lockForUpdate()->first();
                    $lastN = static::withTrashed()
                        ->where('reprogramacion_root_key', $rootKey)
                        ->max('reprogramacion_n');
                    $n = (($lastN ?? 0) + 1);
                    $model->reprogramacion_n = $n;
                    $model->key = $rootKey . '-R' . $n;
                } elseif (!empty($model->parent_key)) {
                    // Subactividad: key anidada {KEY_PADRE}-S{n}, secuencia por padre.
                    // Bloquear la fila del padre serializa los inserts concurrentes de -Sn.
                    static::where('key', $model->parent_key)->lockForUpdate()->first();
                    $lastNum = static::withTrashed()
                        ->where('parent_key', $model->parent_key)
                        ->where('key', 'like', $model->parent_key . '-S%')
                        ->max(DB::raw("CAST(SUBSTRING_INDEX(`key`, '-S', -1) AS UNSIGNED)"));
                    $model->key = $model->parent_key . '-S' . (($lastNum ?? 0) + 1);
                } else {
                    // Actividad top-level: secuencia por espacio (solo top-level).
                    // MAX del sufijo numérico (no COUNT) para sobrevivir soft-deletes y
                    // race conditions; el llamador debe envolver en DB::transaction().
                    // Bloquear la fila del espacio (gp_projects) serializa los inserts
                    // concurrentes de actividades top-level del mismo espacio.
                    $prefix = strtoupper($model->project ?? config('gestion-proyectos.key_prefix', 'PROY'));
                    GpProject::where('key', $model->project)->lockForUpdate()->first();
                    $lastNum = static::withTrashed()
                        ->where('project', $model->project)
                        ->whereNull('parent_key')
                        ->whereNull('reprogramacion_root_key')
                        ->max(DB::raw('CAST(SUBSTRING_INDEX(`key`, \'-\', -1) AS UNSIGNED)'));
                    $model->key = $prefix . '-' . str_pad(($lastNum ?? 0) + 1, 4, '0', STR_PAD_LEFT);
                }
            }

            // Calcular Fecha Límite si hay Fecha Inicio + Días Estimados.
            // dias_estimados === 0 es válido (issue del mismo día): Fecha Límite = Inicio.
            if ($model->start_date && $model->dias_estimados !== null) {
                $model->fecha_limite = \Carbon\Carbon::parse($model->start_date)
                    ->addDays($model->dias_estimados);
            }

            // Auto-sincronizar fechas de etapa con el estado inicial (si se crea ya avanzado).
            // Stage = día que entra a "En Revisión"; Producción = día que pasa a "Finalizado".
            if ($model->status === 'En Revisión' && !$model->fecha_entrega) {
                $model->fecha_entrega = now()->toDateString();
            }
            if ($model->status === 'Finalizado') {
                if (!$model->fecha_aprobacion) $model->fecha_aprobacion = now()->toDateString();
                if (!$model->aprobado_por_id)  $model->aprobado_por_id  = Auth::id();
            }

            // Sincronizar solicitado_por con reporter->name SOLO si no se provee uno manual.
            // Así un nombre escrito a mano (ej. "Cliente Externo") no se pierde al asignar reporter.
            if (empty($model->solicitado_por) && $model->reporter_id) {
                $user = User::find($model->reporter_id);
                if ($user) {
                    $model->solicitado_por = $user->name;
                }
            }
        });

        // Regla de fechas: una Subactividad no puede iniciar antes que su Actividad padre.
        static::saving(function (self $model) {
            if (!empty($model->parent_key) && $model->start_date) {
                $parent = static::where('key', $model->parent_key)->first();
                if ($parent && $parent->start_date
                    && \Carbon\Carbon::parse($model->start_date)->lt(\Carbon\Carbon::parse($parent->start_date))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'start_date' => 'La fecha de inicio de la subactividad no puede ser anterior a la de su actividad padre ('
                            . \Carbon\Carbon::parse($parent->start_date)->toDateString() . ').',
                    ]);
                }
            }
        });

        // Lógica automática de fechas en transiciones de estado
        static::updating(function (self $model) {
            // Sincronizar solicitado_por al cambiar reporter_id, pero SIN pisar un valor
            // manual: solo se auto-rellena si el usuario no lo está editando en este request
            // (no isDirty) y está vacío. Nunca se anula un nombre escrito a mano.
            if ($model->isDirty('reporter_id')
                && !$model->isDirty('solicitado_por')
                && empty($model->solicitado_por)
                && $model->reporter_id) {
                $user = User::find($model->reporter_id);
                if ($user) {
                    $model->solicitado_por = $user->name;
                }
            }

            // Sincronización Fechas ↔ Días Estimados:
            //  - Si se cambia dias_estimados explícitamente, él manda: Fecha Límite = Inicio + días.
            //  - Si se cambia Inicio (start_date) o Fecha Límite sin tocar días, se recalculan los
            //    Días manteniendo el otro extremo como ancla: días = Fecha Límite − Inicio.
            if ($model->isDirty('dias_estimados')) {
                $start = $model->start_date ?? $model->getOriginal('start_date');
                $dias  = $model->dias_estimados;
                // 0 días es válido (mismo día): Fecha Límite = Inicio + 0 = Inicio.
                if ($start && $dias !== null) {
                    $model->fecha_limite = \Carbon\Carbon::parse($start)->addDays($dias);
                }
            } elseif ($model->isDirty(['start_date', 'fecha_limite'])) {
                $start  = $model->start_date  ?? $model->getOriginal('start_date');
                $limite = $model->fecha_limite ?? $model->getOriginal('fecha_limite');
                if ($start && $limite) {
                    // diffInDays CON SIGNO (false): si la Fecha Límite queda ANTES del Inicio,
                    // el diff es negativo y el guard >= 0 evita guardar días negativos espurios.
                    // diff == 0 (mismo día) sí se guarda como 0 días estimados.
                    $diff = (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($limite), false);
                    if ($diff >= 0) {
                        $model->dias_estimados = $diff;
                    }
                }
            }

            // Solo actuar si el status está cambiando
            if (!$model->isDirty('status')) {
                return;
            }

            $newStatus = $model->status;
            $criticals = config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']);

            // → "En Revisión": Fecha Stage = el día de CADA entrada a este estado.
            //   Si rebota (En Curso/Pendiente) y vuelve a Revisión, se reescribe con la nueva fecha.
            if ($newStatus === 'En Revisión') {
                $model->fecha_entrega = now()->toDateString();
            }

            // → "Finalizado": Fecha Producción = el día que se finaliza + quién aprueba.
            if ($newStatus === 'Finalizado') {
                $model->fecha_aprobacion = now()->toDateString();
                if (Auth::id()) {
                    $model->aprobado_por_id = Auth::id();
                }
            }
        });

        // Cierre 100% MANUAL: NO hay auto-cierre del root reprogramado. El gestor finaliza/cancela
        // a mano cada registro (con Aprobador + evidencia). El roll-up del service solo exige que
        // las subactividades/tareas estén cerradas antes de finalizar su actividad padre.

        // Cascada de borrado: arrastra Subactividades, Tareas, versiones -Rn y campos personalizados.
        static::deleting(function (self $model) {
            $subs    = static::where('parent_key', $model->key)->get();
            $tareas  = GpSubTarea::where('parent_key', $model->key)->get();
            // Versiones de reprogramación (-Rn) colgadas de esta actividad raíz; si no se
            // arrastran quedan huérfanas. Cada -Rn cascada sus propios hijos por este hook.
            $reprogs = static::where('reprogramacion_root_key', $model->key)->get();
            if ($model->isForceDeleting()) {
                $subs->each->forceDelete();
                $tareas->each->forceDelete();
                $reprogs->each->forceDelete();

                // Force-delete: limpiar historial de actividad y sus adjuntos en disco
                // (GpActivityHistory es inmutable, sin soft-delete → se purga aquí).
                $historial = GpActivityHistory::where('tarea_key', $model->key)->get();
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
                GpActivityHistory::where('tarea_key', $model->key)->delete();
            } else {
                $subs->each->delete();
                $tareas->each->delete();
                $reprogs->each->delete();
            }
            // GpCustomFieldValue no tiene soft-delete; se purga siempre para evitar acumulación.
            GpCustomFieldValue::where('proyecto_id', $model->id)->delete();
        });
    }

}
