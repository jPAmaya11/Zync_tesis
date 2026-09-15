<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

/**
 * Crea un espacio (proyecto SCRUM). Requiere admin global.
 *
 * Replica la lógica del controlador: purga soft-deleted con la misma key, crea,
 * sincroniza equipos, siembra campos por defecto e invalida el caché.
 */
class CrearEspacioTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-espacio';
    }

    public function description(): string
    {
        return 'Crea un espacio (proyecto SCRUM). Requiere permiso global gestion-proyectos.admin. '
            . 'La "key" es 1-5 caracteres alfanuméricos en MAYÚSCULAS y única (ej. ERP). '
            . 'owner_account_id define el propietario (por defecto, vos).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Clave del espacio: 1-5 caracteres alfanuméricos en MAYÚSCULAS, única. Obligatorio.')->required();
        $schema->string('name')->description('Nombre del espacio. Obligatorio.')->required();
        $schema->string('space_type')->description('Tipo de espacio. Solo SCRUM_PROJECT (valor por defecto).');
        $schema->string('prefix')->description('Prefijo para las keys de las tareas (default = la key).');
        $schema->string('categoria')->description('Categoría libre (texto).');
        $schema->integer('space_category_id')->description('ID de categoría (de listar-categorias).');
        $schema->string('icon')->description('Ícono/emoji del espacio.');
        $schema->string('owner_account_id')->description('account_id del propietario (default: el usuario del token).');
        $schema->raw('team_ids', ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de equipos a vincular (de listar-equipos).']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $user = $this->user();
        if (! $user->can('gestion-proyectos.admin')) {
            return ToolResult::error('Crear espacios requiere permiso de administrador (gestion-proyectos.admin).');
        }

        if (isset($arguments['key']) && is_string($arguments['key'])) {
            $arguments['key'] = strtoupper(trim($arguments['key']));
        }

        $data = Validator::make($arguments, [
            'name'              => ['required', 'string', 'max:150'],
            'key'               => ['required', 'string', 'max:5', 'alpha_num', 'uppercase', Rule::unique('gp_projects', 'key')->whereNull('deleted_at')],
            'space_type'        => ['nullable', 'string', 'in:SCRUM_PROJECT'],
            'prefix'            => ['nullable', 'string', 'max:10'],
            'categoria'         => ['nullable', 'string', 'max:100'],
            'space_category_id' => ['nullable', 'integer', 'exists:gp_space_categories,id'],
            'icon'              => ['nullable', 'string'],
            'owner_account_id'  => ['nullable', 'string', 'regex:/^\d+$/'],
            'team_ids'          => ['nullable', 'array'],
            'team_ids.*'        => ['integer', 'exists:gp_teams,id'],
        ])->validate();

        $ownerId = null;
        if (! empty($data['owner_account_id'])) {
            [$ownerId, $ownerErr] = $this->resolveAndValidateUserId($data['owner_account_id'], 'propietario');
            if ($ownerErr) {
                return ToolResult::error($ownerErr);
            }
        }

        // Purgar soft-deleted con la misma key para evitar el choque de la unique index.
        GpProject::onlyTrashed()->where('key', $data['key'])->get()->each(fn (GpProject $p) => $p->forceDelete());

        $project = GpProject::create([
            'key'               => $data['key'],
            'name'              => $data['name'],
            'space_type'        => $data['space_type'] ?? 'SCRUM_PROJECT',
            'prefix'            => $data['prefix'] ?? strtoupper($data['key']),
            'categoria'         => $data['categoria'] ?? null,
            'space_category_id' => $data['space_category_id'] ?? null,
            'icon'              => $data['icon'] ?? null,
            'owner_id'          => $ownerId ?? $user->id,
            'active'            => true,
        ]);

        if (! empty($data['team_ids'])) {
            $project->teams()->sync($data['team_ids']);
            $project->syncMembersFromTeams();
        }

        app(CustomFieldServiceInterface::class)->seedDefaultFields($data['key']);
        Cache::forget('gp.projects');

        return ToolResult::json([
            'created' => true,
            'key'     => $project->key,
            'name'    => $project->name,
            'message' => "Espacio {$project->key} creado.",
        ]);
    }
}
