<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceCategory;

/**
 * Elimina una categoría de espacio. Los espacios que la usaban quedan sin categoría.
 */
#[IsDestructive]
class BorrarCategoriaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-categoria';
    }

    public function description(): string
    {
        return 'Elimina una categoría de espacio por su id. Requiere permiso global gestion-proyectos.admin. '
            . 'Los espacios que la usaban quedan sin categoría. Operación destructiva: confirmá con el usuario antes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('category_id')->description('ID de la categoría a eliminar (de listar-categorias). Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'category_id' => ['required', 'integer'],
        ])->validate();

        if (! $this->user()->can('gestion-proyectos.admin')) {
            return ToolResult::error('Eliminar categorías requiere permiso de administrador (gestion-proyectos.admin).');
        }

        $category = GpSpaceCategory::find($data['category_id']);
        if (! $category) {
            return ToolResult::error('Categoría no encontrada: ' . $data['category_id']);
        }

        // Desvincular espacios (la FK es SET NULL, pero lo hacemos explícito para
        // invalidar el caché de proyectos vía model events).
        GpProject::where('space_category_id', $category->id)->each(function (GpProject $p) {
            $p->update(['space_category_id' => null]);
        });

        $name = $category->name;
        $category->delete();

        return ToolResult::json([
            'deleted'     => true,
            'category_id' => $data['category_id'],
            'message'     => "Categoría \"{$name}\" eliminada.",
        ]);
    }
}
