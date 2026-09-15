<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpTeam;

/**
 * Sincroniza qué equipos están asociados a un espacio. Sus miembros entran como lectores.
 */
class SincronizarEquiposEspacioTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'sincronizar-equipos-espacio';
    }

    public function description(): string
    {
        return 'Define la lista exacta de equipos asociados a un espacio (reemplaza la actual). Los integrantes '
            . 'de esos equipos entran al espacio como "lector" automáticamente; los que ya no estén en ningún '
            . 'equipo vinculado se quitan. Requiere ser administrador/propietario del espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->raw('team_ids', ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de los equipos a asociar (lista completa; [] desvincula todos).']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
            'team_ids'    => ['nullable', 'array', 'max:50'],
            'team_ids.*'  => ['integer', 'exists:gp_teams,id'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar los equipos de ese espacio.');
        }

        $project = GpProject::where('key', $data['project_key'])->first();
        if (! $project) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        // No permitir ASIGNAR equipos inactivos. Los inactivos que YA estaban asignados
        // pueden permanecer (no se rompe el histórico); solo se bloquea agregar nuevos.
        $solicitados = $data['team_ids'] ?? [];
        if (! empty($solicitados)) {
            $inactivos = GpTeam::whereIn('id', $solicitados)->where('is_active', false)->pluck('id')->all();
            if (! empty($inactivos)) {
                $yaAsignados   = $project->teams()->pluck('gp_teams.id')->all();
                $nuevosInactivos = array_values(array_diff($inactivos, $yaAsignados));
                if (! empty($nuevosInactivos)) {
                    return ToolResult::error(
                        'No se pueden asignar equipos inactivos: ' . implode(', ', $nuevosInactivos)
                        . '. Reactivá el equipo antes de asignarlo.'
                    );
                }
            }
        }

        $project->teams()->sync($solicitados);
        $project->syncMembersFromTeams();
        Cache::forget('gp.projects');

        return ToolResult::json([
            'message'  => 'Equipos sincronizados con el espacio.',
            'team_ids' => array_values($data['team_ids'] ?? []),
        ]);
    }
}
