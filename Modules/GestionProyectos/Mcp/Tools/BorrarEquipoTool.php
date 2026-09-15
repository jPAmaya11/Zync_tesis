<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpTeam;

/**
 * Elimina un equipo por su id y re-sincroniza los miembros de sus espacios. Requiere admin global.
 */
#[IsDestructive]
class BorrarEquipoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-equipo';
    }

    public function description(): string
    {
        return 'Elimina un equipo por su id. Requiere permiso global gestion-proyectos.admin. Tras borrarlo, '
            . 're-sincroniza los miembros de los espacios donde estaba (depura accesos que sólo venían de él). '
            . 'Operación destructiva: confirmá con el usuario antes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('team_id')->description('ID del equipo a eliminar (de listar-equipos). Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'team_id' => ['required', 'integer'],
        ])->validate();

        if (! $this->user()->can('gestion-proyectos.admin')) {
            return ToolResult::error('Eliminar equipos requiere permiso de administrador (gestion-proyectos.admin).');
        }

        $team = GpTeam::with('projects')->find($data['team_id']);
        if (! $team) {
            return ToolResult::error('Equipo no encontrado: ' . $data['team_id']);
        }

        $projects = $team->projects;
        $name = $team->name;
        $team->delete();

        foreach ($projects as $project) {
            $project->syncMembersFromTeams();
        }
        Cache::forget('gp.projects');

        return ToolResult::json([
            'deleted' => true,
            'team_id' => $data['team_id'],
            'message' => "Equipo \"{$name}\" eliminado.",
        ]);
    }
}
