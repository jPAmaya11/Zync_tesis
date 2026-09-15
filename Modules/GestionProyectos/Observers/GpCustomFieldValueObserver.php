<?php

namespace Modules\GestionProyectos\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpCustomField;
use Modules\GestionProyectos\Models\GpCustomFieldValue;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Registra en gp_audit_log los cambios en campos personalizados.
 *
 * Dado que los valores están en una tabla separada, este observer captura
 * las actualizaciones que el ProyectoObserver ignora.
 *
 * Cachés estáticos por request para evitar N+1 en actualizaciones masivas:
 *  - $proyectoCache  — Proyecto por tarea_key
 *  - $fieldCache     — GpCustomField por custom_field_id
 */
class GpCustomFieldValueObserver
{
    private static array $proyectoCache = [];
    private static array $fieldCache    = [];

    private function resolveProyecto(int $proyectoId): ?Proyecto
    {
        if (!array_key_exists($proyectoId, self::$proyectoCache)) {
            self::$proyectoCache[$proyectoId] = Proyecto::find($proyectoId);
        }
        return self::$proyectoCache[$proyectoId];
    }

    private function resolveField(int $fieldId): ?GpCustomField
    {
        if (!array_key_exists($fieldId, self::$fieldCache)) {
            self::$fieldCache[$fieldId] = GpCustomField::find($fieldId);
        }
        return self::$fieldCache[$fieldId];
    }

    public function saved(GpCustomFieldValue $val): void
    {
        // Si no cambió el valor y no es nuevo, no registrar nada
        if (!$val->wasRecentlyCreated && !$val->wasChanged('value')) {
            return;
        }

        $proyecto = $this->resolveProyecto($val->proyecto_id);
        $field    = $this->resolveField($val->custom_field_id);

        if (!$proyecto || !$field) return;

        $userId   = Auth::id();
        $userName = $userId ? (Auth::user()?->name ?? null) : null;

        $oldValue = $val->wasRecentlyCreated ? null : $val->getOriginal('value');
        $newValue = $val->value;

        // Evitar log si el valor es el mismo (null vs "")
        if ($oldValue === $newValue) return;

        GpAuditLog::create([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'model_type' => 'Proyecto',
            'model_id'   => $proyecto->id,
            'model_key'  => $proyecto->key,
            'action'     => 'updated',
            'old_values' => [$field->name => $oldValue],
            'new_values' => [$field->name => $newValue],
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);
    }
}

