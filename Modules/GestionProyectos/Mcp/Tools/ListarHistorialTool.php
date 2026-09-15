<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Lista el Historial de Actividades (comentarios / evidencia) de una tarea.
 */
#[IsReadOnly]
class ListarHistorialTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-historial';
    }

    public function description(): string
    {
        return 'Lista el Historial de Actividades (comentarios y evidencia) de una tarea por su key, '
            . 'del más reciente al más antiguo.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Key de la tarea, ej. ERP-0123. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'key' => ['required', 'string'],
        ])->validate();

        $proyecto = Proyecto::where('key', $data['key'])->first();
        if (! $proyecto) {
            return ToolResult::error('Tarea no encontrada: ' . $data['key']);
        }
        if (! $this->canAccessProject($this->user(), $proyecto->project)) {
            return ToolResult::error('No tenés acceso a esa tarea.');
        }

        $historial = GpActivityHistory::where('tarea_key', $proyecto->key)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => $e->toFrontend())
            ->values()
            ->all();

        return ToolResult::json(['historial' => $historial]);
    }
}
