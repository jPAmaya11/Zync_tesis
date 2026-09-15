<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpCategoria;

/**
 * Lista las categorías de ACTIVIDAD propias de un espacio (tabla gp_categorias).
 *
 * OJO — no confundir con "listar-categorias", que devuelve las categorías de ESPACIOS
 * (GpSpaceCategory) y es otra cosa distinta.
 *
 * El catálogo crece solo, igual que el de etiquetas: se alimenta cuando alguien escribe
 * una categoría nueva al crear o editar una actividad. Por eso conviene consultarlo antes
 * de crear, así se reusa el nombre exacto en vez de generar duplicados casi iguales.
 */
#[IsReadOnly]
class ListarCategoriasActividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-categorias-actividad';
    }

    public function description(): string
    {
        return 'Lista las categorías de ACTIVIDAD ya existentes en un espacio, con su id y nombre. '
            . 'Usala ANTES de crear o actualizar una actividad para reusar una categoría existente '
            . '(si mandás una que no está, se da de alta sola y el catálogo se llena de duplicados). '
            . 'No confundir con "listar-categorias", que son las categorías de los ESPACIOS. '
            . 'Requiere acceso al espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
        ])->validate();

        if (! $this->canAccessProject($this->user(), $data['project_key'])) {
            return ToolResult::error('No tenés acceso a ese espacio.');
        }

        $categorias = GpCategoria::forProject($data['project_key'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values()
            ->all();

        return ToolResult::json(['categorias' => $categorias]);
    }
}
