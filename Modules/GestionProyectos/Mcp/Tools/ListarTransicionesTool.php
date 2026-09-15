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
 * Devuelve las transiciones (estados destino) válidas para una tarea.
 */
#[IsReadOnly]
class ListarTransicionesTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-transiciones';
    }

    public function description(): string
    {
        return 'Devuelve los estados a los que se puede mover una tarea (transiciones válidas). '
            . 'Recordá: los estados críticos (Finalizado, Reprogramado, Cancelado) sólo los aplica un aprobador, '
            . 'y Finalizado/Reprogramado requieren un comentario de evidencia previo (agregar-comentario).';
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

        return ToolResult::json([
            'transiciones' => $this->service()->getTransitions($proyecto->key),
        ]);
    }
}
