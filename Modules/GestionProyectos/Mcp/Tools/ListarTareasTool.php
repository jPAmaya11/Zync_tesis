<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSubTarea;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Lista las tareas de una actividad o subactividad padre (Blueprint §2.5).
 */
#[IsReadOnly]
class ListarTareasTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-tareas';
    }

    public function description(): string
    {
        return 'Lista las tareas de una actividad o subactividad padre por su key (ej. ERP-0123).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Key de la actividad o subactividad padre, ej. ERP-0123. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'key' => ['required', 'string'],
        ])->validate();

        $padre = Proyecto::where('key', $data['key'])->first();
        if (! $padre) {
            return ToolResult::error('Tarea no encontrada: ' . $data['key']);
        }
        if (! $this->canAccessProject($this->user(), $padre->project)) {
            return ToolResult::error('No tenés acceso a esa tarea.');
        }

        $tareas = GpSubTarea::where('parent_key', $padre->key)
            ->with(['assignee', 'creator'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($s) => $s->toFrontend())
            ->values()
            ->all();

        return ToolResult::json(['tareas' => $tareas]);
    }
}
