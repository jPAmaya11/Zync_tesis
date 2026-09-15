<?php

namespace Modules\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Registro INMUTABLE de auditoría del módulo de Roles.
 * No admite updates ni deletes (ver boot): una vez escrito, queda fijo.
 */
class RoleAuditLog extends Model
{
    protected $table = 'role_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'role_id',
        'role_name',
        'action',
        'target_user_id',
        'target_user_name',
        'old_values',
        'new_values',
        'description',
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

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ─── Boot: inmutabilidad + created_at ─────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Inmutable: nadie puede editar ni borrar una entrada de auditoría.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /** Etiqueta legible de cada acción para el frontend. */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'created'             => 'Rol creado',
            'deleted'             => 'Rol eliminado',
            'restored'            => 'Rol restaurado',
            'name_changed'        => 'Nombre cambiado',
            'permissions_changed' => 'Permisos modificados',
            'user_assigned'       => 'Usuario asignado',
            'user_unassigned'     => 'Usuario removido',
            default               => $action,
        };
    }

    public function toFrontend(): array
    {
        return [
            'id'               => $this->id,
            'action'           => $this->action,
            'action_label'     => self::actionLabel($this->action),
            'user_name'        => $this->user_name ?? 'Sistema',
            'role_name'        => $this->role_name,
            'target_user_name' => $this->target_user_name,
            'old_values'       => $this->old_values,
            'new_values'       => $this->new_values,
            'description'      => $this->description,
            'ip_address'       => $this->ip_address,
            'created_at'       => $this->created_at?->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
        ];
    }
}
