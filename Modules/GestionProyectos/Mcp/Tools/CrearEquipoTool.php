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
 * Crea un equipo. Si se pasa project_key, lo crea y lo vincula a ese espacio
 * (requiere 'manage'); si no, crea un equipo global (requiere admin).
 */
class CrearEquipoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-equipo';
    }

    public function description(): string
    {
        return 'Crea un equipo con miembros (member_account_ids). Si pasás "project_key" lo vincula a ese espacio '
            . '(requiere ser administrador/propietario del espacio); sin project_key crea un equipo global '
            . '(requiere permiso global gestion-proyectos.admin).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('name')->description('Nombre del equipo. Obligatorio.')->required();
        $schema->string('description')->description('Descripción del equipo.');
        $schema->raw('member_account_ids', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'account_ids de los miembros del equipo.']);
        $schema->string('project_key')->description('Key del espacio a vincular (opcional). Sin esto, el equipo es global.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'member_account_ids'   => ['nullable', 'array', 'max:100'],
            'member_account_ids.*' => ['string', 'regex:/^\d+$/'],
            'project_key'          => ['nullable', 'string'],
        ])->validate();

        $user = $this->user();
        $projectKey = $data['project_key'] ?? null;
        $project = null;

        if ($projectKey) {
            if (! $user->can('manage', $projectKey)) {
                return ToolResult::error('No tenés permiso para gestionar equipos de ese espacio.');
            }
            $project = GpProject::where('key', $projectKey)->first();
            if (! $project) {
                return ToolResult::error('Espacio no encontrado: ' . $projectKey);
            }
        } elseif (! $user->can('gestion-proyectos.admin')) {
            return ToolResult::error('Crear un equipo global requiere permiso de administrador (gestion-proyectos.admin).');
        }

        $team = GpTeam::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by'  => $user->id,
        ]);

        [$memberIds, $memberErr] = $this->resolveMemberIds($data['member_account_ids'] ?? []);
        if ($memberErr) {
            $team->forceDelete();
            return ToolResult::error($memberErr);
        }
        if (! empty($memberIds)) {
            $team->members()->sync($memberIds);
        }

        if ($project) {
            $project->teams()->attach($team->id);
            $project->syncMembersFromTeams();
            Cache::forget('gp.projects');
        }

        return ToolResult::json($team->load('members')->toFrontend());
    }

    /**
     * @param array<int,string> $accountIds
     * @return array{0: int[], 1: string|null}  [ids, errorMessage]
     */
    private function resolveMemberIds(array $accountIds): array
    {
        $ids = [];
        foreach ($accountIds as $a) {
            [$id, $err] = $this->resolveAndValidateUserId((string) $a, 'miembro');
            if ($err) {
                return [[], $err];
            }
            if ($id) {
                $ids[] = $id;
            }
        }

        return [array_values(array_unique($ids)), null];
    }
}
