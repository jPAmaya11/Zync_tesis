<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Miembro de un espacio con su rol — Blueprint §2.1
 *
 * Roles: propietario | administrador | ejecutor | aprobador | lector
 */
class GpSpaceMember extends Model
{
    protected $table = 'gp_space_members';

    protected $fillable = [
        'project_key',
        'user_id',
        'role',
        'team_synced',
        'suspended',
    ];

    protected $casts = [
        'team_synced' => 'boolean',
        'suspended'   => 'boolean',
    ];

    // ─── Roles que pueden mover a Finalizado / Reprogramado (§2.4) ─────────
    // Implementador = ejecutor + aprobador: también puede aprobar estados críticos.
    public const APPROVER_ROLES = ['propietario', 'administrador', 'aprobador', 'implementador'];

    // ─── Roles que pueden crear/editar tareas (escritura) ──────────────────
    // Implementador escribe igual que el ejecutor.
    public const WRITE_ROLES = ['propietario', 'administrador', 'ejecutor', 'implementador'];

    // ─── Relaciones ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(GpProject::class, 'project_key', 'key');
    }

    // ─── Helpers estáticos ──────────────────────────────────────────────────

    /**
     * Devuelve el rol de un usuario en un espacio, o null si no es miembro.
     *
     * CHOKEPOINT DE ACCESO: excluye membresías SUSPENDIDAS (acceso cerrado por equipo
     * inactivo). Como canApprove/canWrite/canManage/isOwner y canAccessProject pasan por
     * aquí, una membresía suspendida no concede ningún acceso, aunque su rol se conserve.
     */
    public static function roleInSpace(int $userId, string $projectKey): ?string
    {
        return static::where('user_id', $userId)
            ->where('project_key', $projectKey)
            ->where('suspended', false)
            ->value('role');
    }

    /**
     * ¿Puede el usuario aprobar (mover a estados críticos) en el espacio?
     * Los usuarios con permiso global 'gestion-proyectos.admin' siempre pueden.
     */
    public static function canApprove(int $userId, string $projectKey): bool
    {
        $user = \Modules\User\Models\User::find($userId);
        if ($user && ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin'))) {
            return true;
        }

        $role = static::roleInSpace($userId, $projectKey);

        return $role !== null && in_array($role, self::APPROVER_ROLES, true);
    }

    /**
     * ¿Puede el usuario editar/crear (escritura) en el espacio?
     * Propietario, Administrador y Ejecutor pueden crear/editar tareas.
     * Aprobador es solo lectura + aprobación de estados finales.
     */
    public static function canWrite(int $userId, string $projectKey): bool
    {
        $user = \Modules\User\Models\User::find($userId);
        if (!$user) return false;

        // Bypass: admin global (rol o permiso Spatie)
        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) return true;

        // Fallback: owner_id del proyecto (cubre casos donde aún no hay registro en gp_space_members)
        if (GpProject::where('key', $projectKey)->where('owner_id', $userId)->exists()) return true;

        $role = self::roleInSpace($userId, $projectKey);
        return in_array($role, self::WRITE_ROLES, true);
    }

    /**
     * ¿Es el usuario el PROPIETARIO del espacio?
     * True si es owner_id del proyecto o tiene rol 'propietario' en gp_space_members.
     * No incluye bypass de admin global a propósito: el bypass se resuelve en la Policy.
     */
    public static function isOwner(int $userId, string $projectKey): bool
    {
        if (GpProject::where('key', $projectKey)->where('owner_id', $userId)->exists()) return true;

        return self::roleInSpace($userId, $projectKey) === 'propietario';
    }

    /**
     * ¿Puede el usuario EDITAR DATOS inline (nombre, fechas, responsable, etiquetas,
     * campos personalizados, edición masiva) en el espacio?
     *
     * Regla: admin global ∪ PROPIETARIO ∪ ADMINISTRADOR ∪ IMPLEMENTADOR. Decisión
     * explícita: estos roles editan los datos de la actividad. NO amplía a
     * ejecutor/aprobador/lector. Eliminar y gestionar el espacio siguen en canManage
     * (propietario/administrador) → no altera la jerarquía.
     */
    public static function canInlineEdit(int $userId, string $projectKey): bool
    {
        $user = \Modules\User\Models\User::find($userId);
        if (!$user) return false;

        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) return true;

        if (self::isOwner($userId, $projectKey)) return true;

        return in_array(self::roleInSpace($userId, $projectKey), ['administrador', 'implementador'], true);
    }

    /**
     * ¿Puede el usuario gestionar (eliminar, configurar miembros) en el espacio?
     */
    public static function canManage(int $userId, string $projectKey): bool
    {
        $user = \Modules\User\Models\User::find($userId);
        if (!$user) return false;

        // Bypass: admin global (rol o permiso Spatie)
        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) return true;

        // Fallback: owner_id del proyecto (cubre casos donde aún no hay registro en gp_space_members)
        if (GpProject::where('key', $projectKey)->where('owner_id', $userId)->exists()) return true;

        // Verificar rol específico en el espacio
        $role = self::roleInSpace($userId, $projectKey);
        // Solo Propietario y Administrador pueden gestionar
        return in_array($role, ['propietario', 'administrador']);
    }

    /**
     * ¿Puede el usuario VER el espacio? Regla de visibilidad canónica del módulo:
     * admin global ∪ miembro VIGENTE (no suspendido) ∪ owner_id del proyecto.
     * Úsese como gate en endpoints que reciben un {key}/{projectKey} adivinable.
     */
    public static function canSeeProject(int $userId, string $projectKey): bool
    {
        $user = \Modules\User\Models\User::find($userId);
        if (!$user) return false;

        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) return true;

        if (self::roleInSpace($userId, $projectKey) !== null) return true;

        return GpProject::where('key', $projectKey)->where('owner_id', $userId)->exists();
    }

    /**
     * Claves de los espacios VISIBLES para el usuario (miembro vigente ∪ owner).
     * Devuelve null para admin global (= todos, sin filtrar). Es la MISMA regla que
     * aplica el index del módulo; centralizada aquí para que no haya dos fuentes.
     */
    public static function visibleProjectKeys(User $user): ?array
    {
        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) {
            return null;
        }

        return static::where('user_id', $user->id)
            ->where('suspended', false)
            ->pluck('project_key')
            ->merge(GpProject::where('owner_id', $user->id)->pluck('key'))
            ->unique()
            ->values()
            ->all();
    }

    // ─── Accessors ──────────────────────────────────────────────────────────

    /** Etiqueta legible del rol en español. */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'propietario'   => 'Propietario',
            'administrador' => 'Administrador',
            'ejecutor'      => 'Ejecutor',
            'aprobador'     => 'Aprobador',
            'implementador' => 'Implementador',
            default         => ucfirst($this->role),
        };
    }

    public function toFrontend(): array
    {
        return [
            'id'         => $this->id,
            'project_key'=> $this->project_key,
            'user_id'    => $this->user_id,
            'role'       => $this->role,
            'role_label' => $this->role_label,
            'team_synced'=> (bool) $this->team_synced,
            'suspended'  => (bool) $this->suspended,
            'user'       => $this->user ? [
                'id'           => $this->user->id,
                'account_id'   => (string) $this->user->id,
                'display_name' => $this->user->name,
                'email'        => $this->user->email,
                'avatar_url'   => $this->user->avatar_url,
            ] : null,
        ];
    }
}
