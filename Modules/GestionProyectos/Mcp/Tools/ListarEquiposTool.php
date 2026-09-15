<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpTeam;

/**
 * Lista equipos: globales, o los asociados a un espacio si se pasa project_key.
 */
#[IsReadOnly]
class ListarEquiposTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-equipos';
    }

    public function description(): string
    {
        return 'Lista equipos con sus miembros. Sin argumentos devuelve todos los equipos globales; '
            . 'pasá "project_key" para listar sólo los asociados a ese espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio para filtrar sus equipos (opcional).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['nullable', 'string'],
        ])->validate();

        $user = $this->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin', 'super_admin'])
                || $user->can('gestion-proyectos.admin');

        if (! empty($data['project_key'])) {
            // Con espacio explícito: verificar acceso.
            if (! $this->canAccessProject($user, $data['project_key'])) {
                return ToolResult::error('No tenés acceso a ese espacio.');
            }
            $project = GpProject::where('key', $data['project_key'])->first();
            if (! $project) {
                return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
            }
            $teams = $project->teams()->with('members')->orderBy('name')->get();
        } elseif ($isAdmin) {
            // Admin global: ve todos los equipos del sistema.
            $teams = GpTeam::with('members')->orderBy('name')->get();
        } else {
            // Usuario normal (ejecutor/aprobador/lector): solo equipos de sus espacios.
            // Así puede pedir "listame equipos" sin saber qué es project_key.
            $myProjectKeys = \Modules\GestionProyectos\Models\GpSpaceMember::where('user_id', $user->id)
                ->pluck('project_key');

            $teamIds = \Illuminate\Support\Facades\DB::table('gp_project_teams')
                ->whereIn('project_key', $myProjectKeys)
                ->pluck('team_id');

            $teams = GpTeam::with('members')
                ->whereIn('id', $teamIds)
                ->orderBy('name')
                ->get();
        }

        return ToolResult::json([
            'equipos' => $teams->map(fn ($t) => $t->toFrontend())->values()->all(),
        ]);
    }
}
