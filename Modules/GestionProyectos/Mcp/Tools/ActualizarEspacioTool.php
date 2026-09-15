<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;

/**
 * Actualiza un espacio existente. Requiere 'manage'. Sólo cambia los campos enviados.
 */
class ActualizarEspacioTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-espacio';
    }

    public function description(): string
    {
        return 'Actualiza un espacio existente (nombre, categoría, ícono, propietario, equipos). Requiere ser '
            . 'administrador/propietario del espacio. Sólo cambia los campos que envíes. Si enviás team_ids, '
            . 'reemplaza los equipos vinculados y re-sincroniza miembros.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio a actualizar, ej. ERP. Obligatorio.')->required();
        $schema->string('name')->description('Nuevo nombre. Obligatorio.')->required();
        $schema->string('space_type')->description('Tipo de espacio. Solo SCRUM_PROJECT.');
        $schema->string('prefix')->description('Prefijo de las keys de tareas.');
        $schema->string('categoria')->description('Categoría libre (texto).');
        $schema->integer('space_category_id')->description('ID de categoría (de listar-categorias).');
        $schema->string('icon')->description('Ícono/emoji del espacio.');
        $schema->string('owner_account_id')->description('account_id del nuevo propietario.');
        $schema->raw('team_ids', ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de equipos vinculados (lista completa; reemplaza la actual).']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key'       => ['required', 'string'],
            'name'              => ['required', 'string', 'max:150'],
            'space_type'        => ['sometimes', 'nullable', 'string', 'in:SCRUM_PROJECT'],
            'prefix'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'categoria'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'space_category_id' => ['sometimes', 'nullable', 'integer', 'exists:gp_space_categories,id'],
            'icon'              => ['sometimes', 'nullable', 'string'],
            'owner_account_id'  => ['sometimes', 'nullable', 'string', 'regex:/^\d+$/'],
            'team_ids'          => ['sometimes', 'nullable', 'array'],
            'team_ids.*'        => ['integer', 'exists:gp_teams,id'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar ese espacio.');
        }

        $project = GpProject::where('key', $data['project_key'])->first();
        if (! $project) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        $update = ['name' => $data['name']];
        foreach (['space_type', 'prefix', 'categoria', 'space_category_id', 'icon'] as $f) {
            if (array_key_exists($f, $data)) {
                $update[$f] = $data[$f];
            }
        }

        if (array_key_exists('owner_account_id', $data)) {
            [$ownerId, $ownerErr] = $this->resolveAndValidateUserId($data['owner_account_id'], 'propietario');
            if ($ownerErr) {
                return ToolResult::error($ownerErr);
            }
            $update['owner_id'] = $ownerId;
        }

        $project->update($update);

        if (array_key_exists('team_ids', $data)) {
            $project->teams()->sync($data['team_ids'] ?? []);
            $project->syncMembersFromTeams();
        }

        Cache::forget('gp.projects');

        return ToolResult::json([
            'updated' => true,
            'key'     => $project->key,
            'name'    => $project->name,
            'message' => "Espacio '{$project->name}' actualizado.",
        ]);
    }
}
