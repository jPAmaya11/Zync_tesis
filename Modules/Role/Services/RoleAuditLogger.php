<?php

namespace Modules\Role\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Role\Models\Role;
use Modules\Role\Models\RoleAuditLog;
use Modules\User\Models\User;

/**
 * Punto único para registrar la auditoría del módulo de Roles.
 * Captura quién (actor), sobre qué rol, el diff y el contexto de la petición.
 */
class RoleAuditLogger
{
    /** Registro base: arma el contexto (actor, IP, agente, url) y persiste. */
    public static function record(string $action, ?Role $role, array $data = []): void
    {
        $actor   = Auth::user();
        $request = request();

        RoleAuditLog::create(array_merge([
            'user_id'    => $actor?->id,
            'user_name'  => $actor?->name ?? 'Sistema',
            'role_id'    => $role?->id,
            'role_name'  => $data['role_name'] ?? $role?->name,
            'action'     => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url'        => $request?->fullUrl(),
            'method'     => $request?->method(),
        ], array_intersect_key($data, array_flip([
            'role_name', 'target_user_id', 'target_user_name',
            'old_values', 'new_values', 'description',
        ]))));
    }

    public static function created(Role $role, array $newValues = []): void
    {
        self::record('created', $role, ['new_values' => $newValues ?: null]);
    }

    public static function deleted(Role $role): void
    {
        self::record('deleted', $role, [
            'role_name'  => $role->name,
            'old_values' => ['name' => $role->name],
        ]);
    }

    public static function nameChanged(Role $role, ?string $old, ?string $new): void
    {
        self::record('name_changed', $role, [
            'old_values' => ['name' => $old],
            'new_values' => ['name' => $new],
        ]);
    }

    /** Cambio de permisos: registra los agregados y removidos. */
    public static function permissionsChanged(Role $role, array $added, array $removed): void
    {
        self::record('permissions_changed', $role, [
            'old_values' => ['removidos' => array_values($removed)],
            'new_values' => ['agregados' => array_values($added)],
            'description' => sprintf('+%d / -%d permisos', count($added), count($removed)),
        ]);
    }

    public static function userAssigned(Role $role, User $target): void
    {
        self::record('user_assigned', $role, [
            'target_user_id'   => $target->id,
            'target_user_name' => $target->name,
        ]);
    }

    public static function userUnassigned(Role $role, User $target): void
    {
        self::record('user_unassigned', $role, [
            'target_user_id'   => $target->id,
            'target_user_name' => $target->name,
        ]);
    }
}
