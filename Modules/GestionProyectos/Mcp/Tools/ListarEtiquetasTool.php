<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;

/**
 * Lista las etiquetas propias de un espacio.
 */
#[IsReadOnly]
class ListarEtiquetasTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-etiquetas';
    }

    public function description(): string
    {
        return 'Lista las etiquetas (labels) propias de un espacio, con su id y nombre. Requiere acceso al espacio.';
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

        $etiquetas = GpLabel::forProject($data['project_key'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values()
            ->all();

        return ToolResult::json(['etiquetas' => $etiquetas]);
    }
}
