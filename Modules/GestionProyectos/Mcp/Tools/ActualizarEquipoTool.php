<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpTeam;

/**
 * Actualiza un equipo (nombre, descripción, miembros) por su id. Requiere admin global.
 */
class ActualizarEquipoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-equipo';
    }

    public function description(): string
    {
        return 'Actualiza un equipo por su id (nombre, descripción y/o miembros). Requiere permiso global '
            . 'gestion-proyectos.admin. Si enviás member_account_ids, reemplaza la lista de miembros y '
            . 're-sincroniza los espacios donde está el equipo.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('team_id')->description('ID del equipo (de listar-equipos). Obligatorio.')->required();
        $schema->string('name')->description('Nuevo nombre.');
        $schema->string('description')->description('Nueva descripción.');
        $schema->boolean('is_active')->description('Activar (true) o desactivar (false) el equipo. Desactivar NO toca a sus miembros ni a sus asignaciones; solo deja de ofrecerse para nuevas asignaciones.');
        $schema->raw('member_account_ids', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'account_ids de los miembros (reemplaza los actuales).']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'team_id'              => ['required', 'integer'],
            'name'                 => ['sometimes', 'string', 'max:100'],
            'description'          => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'            => ['sometimes', 'boolean'],
            'member_account_ids'   => ['sometimes', 'nullable', 'array', 'max:100'],
            'member_account_ids.*' => ['string', 'regex:/^\d+$/'],
        ])->validate();

        if (! $this->user()->can('gestion-proyectos.admin')) {
            return ToolResult::error('Editar equipos requiere permiso de administrador (gestion-proyectos.admin).');
        }

        $team = GpTeam::find($data['team_id']);
        if (! $team) {
            return ToolResult::error('Equipo no encontrado: ' . $data['team_id']);
        }

        if (array_key_exists('name', $data)) {
            $team->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $team->description = $data['description'];
        }
        if (array_key_exists('is_active', $data)) {
            // "Puerta cerrada" reversible: al desactivar se suspende a los miembros que
            // accedían POR este equipo; al reactivar se des-suspenden (rol intacto).
            $team->is_active = $data['is_active'];
        }
        $team->save();

        if (array_key_exists('member_account_ids', $data)) {
            $ids = [];
            foreach ($data['member_account_ids'] ?? [] as $a) {
                [$id, $err] = $this->resolveAndValidateUserId((string) $a, 'miembro');
                if ($err) {
                    return ToolResult::error($err);
                }
                if ($id) {
                    $ids[] = $id;
                }
            }
            $team->members()->sync(array_values(array_unique($ids)));
        }

        // Re-sincronizar accesos si cambiaron los miembros o el estado activo/inactivo.
        if (array_key_exists('member_account_ids', $data) || array_key_exists('is_active', $data)) {
            foreach ($team->projects as $project) {
                $project->syncMembersFromTeams();
            }
        }

        Cache::forget('gp.projects');

        return ToolResult::json($team->load('members')->toFrontend());
    }
}
