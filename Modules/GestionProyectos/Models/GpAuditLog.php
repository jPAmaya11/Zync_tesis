<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\User\Models\User;

/**
 * Registro inmutable de cambios en campos críticos de una tarea.
 * Escrito por ProyectoObserver en cada updated.
 */
class GpAuditLog extends Model
{
    protected $table = 'gp_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'model_type',
        'model_id',
        'model_key',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Mapa de nombres legibles para los campos rastreados.
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'status'           => 'Estado',
            'assignee_id'      => 'Persona Asignada',
            'reporter_id'      => 'Solicitado Por',
            'aprobado_por_id'  => 'Aprobado Por',
            'validado_por_id'  => 'Validado Por',
            'team_id'          => 'Equipo',
            'priority'         => 'Prioridad',
            'summary'          => 'Actividad',
            'fecha_limite'     => 'Fecha Límite',
            'start_date'       => 'Fecha Inicio',
            'dias_estimados'   => 'Días Estimados',
            'fecha_entrega'    => 'Fecha Entrega',
            'fecha_aprobacion' => 'Fecha Aprobación',
            'software'         => 'Software',
            'entorno'          => 'Entorno',
            'impacto'          => 'Impacto',
            'labels'           => 'Etiquetas',
            'project'          => 'Proyecto',
            default            => $field,
        };
    }

    /**
     * Formatea un valor para mostrarlo legible en el frontend.
     */
    public static function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'assignee_id') {
            // El observer guarda el nombre del usuario, no el ID
            return is_string($value) ? $value : (string) $value;
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        // Campos de fecha (solo-fecha): se mostraban crudos como
        // '2026-06-19T00:00:00.000000Z' (el "00:00:00.000000" que se veía como "000" en el
        // timeline). Se normalizan a fecha legible y consistente, sin la hora 00:00 vacía.
        $dateFields = ['start_date', 'fecha_limite', 'fecha_entrega', 'fecha_aprobacion', 'created_at', 'updated_at'];
        if (in_array($field, $dateFields, true)) {
            try {
                return Carbon::parse((string) $value)->locale('es')->isoFormat('D MMM YYYY');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /**
     * Serializa la entrada para el timeline del frontend.
     */
    public function toTimeline(): array
    {
        $changes = [];

        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        // Para 'created'/'deleted' iteramos según corresponda
        $iterateOver = $this->action === 'deleted' ? $old : $new;

        foreach ($iterateOver as $field => $val) {
            $changes[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'old'   => self::formatValue($field, $old[$field] ?? null),
                'new'   => self::formatValue($field, $new[$field] ?? null),
            ];
        }

        return [
            'type'       => 'audit',
            'id'         => 'audit-' . $this->id,
            'created_at' => $this->created_at?->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
            'user_name'  => $this->user_name ?? 'Sistema',
            'action'     => $this->action,
            'changes'    => $changes,
        ];
    }
}
