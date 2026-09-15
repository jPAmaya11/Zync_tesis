<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Lista las subactividades (Proyectos anidados) de una actividad.
 */
#[IsReadOnly]
class ListarSubactividadesTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-subactividades';
    }

    public function description(): string
    {
        return 'Lista las subactividades de una actividad por su key. Las subactividades no aparecen en '
            . 'listar-actividades (que es solo top-level); se consultan con esta tool.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('actividad_key')->description('Key de la actividad padre, ej. ERP-0123. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'actividad_key' => ['required', 'string'],
        ])->validate();

        $padre = Proyecto::where('key', $data['actividad_key'])->first();
        if (! $padre) {
            return ToolResult::error('Actividad no encontrada: ' . $data['actividad_key']);
        }
        if (! $this->canAccessProject($this->user(), $padre->project)) {
            return ToolResult::error('No tenés acceso a ese espacio.');
        }

        $subs = Proyecto::where('parent_key', $padre->key)
            ->with(['assignee', 'reporter', 'creator', 'aprobadoPor', 'team'])
            ->withCount('subTareas')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => $this->toApi($p))
            ->all();

        return ToolResult::json(['subactividades' => $subs]);
    }
}
