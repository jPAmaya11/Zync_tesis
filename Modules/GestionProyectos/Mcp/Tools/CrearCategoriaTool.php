<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSpaceCategory;

/**
 * Crea una categoría de espacio. Requiere admin global.
 */
class CrearCategoriaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-categoria';
    }

    public function description(): string
    {
        return 'Crea una categoría de espacio. Requiere permiso global gestion-proyectos.admin. '
            . 'El nombre debe ser único.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('name')->description('Nombre de la categoría. Obligatorio y único.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:100'],
        ])->validate();

        if (! $this->user()->can('gestion-proyectos.admin')) {
            return ToolResult::error('Crear categorías requiere permiso de administrador (gestion-proyectos.admin).');
        }

        if (GpSpaceCategory::where('name', $data['name'])->exists()) {
            return ToolResult::error('Ya existe una categoría con ese nombre.');
        }

        $category = GpSpaceCategory::create(['name' => $data['name']]);

        return ToolResult::json(['id' => $category->id, 'name' => $category->name]);
    }
}
