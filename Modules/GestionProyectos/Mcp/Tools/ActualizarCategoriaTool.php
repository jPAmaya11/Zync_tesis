<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSpaceCategory;

/**
 * Renombra una categoría de espacio por su id. Requiere admin global.
 */
class ActualizarCategoriaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-categoria';
    }

    public function description(): string
    {
        return 'Renombra una categoría de espacio por su id. Requiere permiso global gestion-proyectos.admin. '
            . 'El nuevo nombre debe ser único.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('category_id')->description('ID de la categoría (de listar-categorias). Obligatorio.')->required();
        $schema->string('name')->description('Nuevo nombre. Obligatorio y único.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'category_id' => ['required', 'integer'],
            'name'        => ['required', 'string', 'max:100'],
        ])->validate();

        if (! $this->user()->can('gestion-proyectos.admin')) {
            return ToolResult::error('Editar categorías requiere permiso de administrador (gestion-proyectos.admin).');
        }

        $category = GpSpaceCategory::find($data['category_id']);
        if (! $category) {
            return ToolResult::error('Categoría no encontrada: ' . $data['category_id']);
        }

        $dup = GpSpaceCategory::where('name', $data['name'])->where('id', '!=', $category->id)->exists();
        if ($dup) {
            return ToolResult::error('Ya existe una categoría con ese nombre.');
        }

        $category->update(['name' => $data['name']]);

        return ToolResult::json(['id' => $category->id, 'name' => $category->name]);
    }
}
