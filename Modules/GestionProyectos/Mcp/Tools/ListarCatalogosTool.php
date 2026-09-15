<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;

#[IsReadOnly]
class ListarCatalogosTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-catalogos';
    }

    public function description(): string
    {
        return 'Devuelve los valores válidos para los campos de una tarea: estados (status), '
            . 'tipos de issue (issue_type), prioridades (priority) y etiquetas (labels) de los '
            . 'espacios a los que tenés acceso.';
    }

    public function handle(array $arguments): ToolResult
    {
        $user = $this->user();

        if (! $user->can('gestion-proyectos.ver')) {
            return ToolResult::error('No tenés acceso al módulo.');
        }

        $svc = $this->service();

        return ToolResult::json([
            'statuses'    => $svc->getStatuses(),
            'issue_types' => array_column($svc->getIssueTypes(), 'name'),
            'priorities'  => array_column($svc->getPriorities(), 'name'),
            'labels'      => $this->accessibleLabels($user),
        ]);
    }

    /**
     * Etiquetas SOLO de los espacios accesibles por el usuario (diseño por-espacio: GpLabel
     * con project_key). Evita la fuga de nombres de etiquetas entre espacios privados.
     * El admin global del módulo ve todas; el resto, solo las de sus espacios (miembro no
     * suspendido o propietario).
     */
    private function accessibleLabels($user): array
    {
        $isAdmin = $user->hasRole(['admin', 'super-admin', 'super_admin'])
            || $user->can('gestion-proyectos.admin');

        if ($isAdmin) {
            return GpLabel::orderBy('name')->pluck('name')->unique()->values()->all();
        }

        $keys = GpSpaceMember::where('user_id', $user->id)
            ->where('suspended', false)
            ->pluck('project_key')
            ->merge(GpProject::where('owner_id', $user->id)->pluck('key'))
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        return GpLabel::whereIn('project_key', $keys)
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }
}
