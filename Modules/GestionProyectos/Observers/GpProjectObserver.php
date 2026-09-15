<?php

namespace Modules\GestionProyectos\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\User\Models\User;

class GpProjectObserver
{
    /**
     * Auditoría de borrado de ESPACIO (soft-delete).
     *
     * Vive en el observer (capa de modelo) para cubrir TODOS los paths de borrado
     * por igual: interfaz web (destroyProject) y MCP (BorrarEspacioTool, que setea
     * el usuario vía Auth::setUser). Una sola fuente de verdad.
     */
    public function deleted(GpProject $project): void
    {
        $userId   = Auth::id();
        $userName = $userId ? (Auth::user()?->name ?? null) : null;

        $ownerName = null;
        if ($project->owner_id) {
            $ownerName = User::whereKey($project->owner_id)->value('name');
        }

        GpAuditLog::create([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'model_type' => 'GpProject',
            'model_id'   => $project->id,
            'model_key'  => $project->key,
            'action'     => 'deleted',
            'old_values' => [
                'name'        => $project->name,
                'key'         => $project->key,
                'description' => $project->description ?? null,
                'owner'       => $ownerName,
            ],
            'new_values' => [],
            'ip_address' => Request::ip(),
            'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
            'url'        => substr(Request::fullUrl(), 0, 500),
            'method'     => Request::method(),
        ]);
    }

    public function updated(GpProject $project): void
    {
        if ($project->isDirty('owner_id')) {
            $newOwnerId = $project->owner_id;

            if ($newOwnerId) {
                // Asegurar que el nuevo dueño tenga el rol de propietario
                GpSpaceMember::updateOrCreate(
                    ['project_key' => $project->key, 'user_id' => $newOwnerId],
                    ['role' => 'propietario']
                );

                // Opcional: degradar a propietarios anteriores si los hubiera
                GpSpaceMember::where('project_key', $project->key)
                    ->where('user_id', '!=', $newOwnerId)
                    ->where('role', 'propietario')
                    ->update(['role' => 'administrador']);
            }
        }
    }
}
