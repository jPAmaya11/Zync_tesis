<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSubTarea;

/**
 * Elimina (soft delete) una tarea por su id. Requiere permiso de gestión (manage).
 */
#[IsDestructive]
class BorrarTareaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-tarea';
    }

    public function description(): string
    {
        return 'Elimina (soft delete) una tarea por su id numérico. Requiere permiso de gestión (manage) '
            . 'en el espacio. Operación destructiva: confirmá con el usuario antes de ejecutarla.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('id')->description('ID numérico de la tarea a eliminar. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'id' => ['required', 'integer'],
        ])->validate();

        $sub = GpSubTarea::find($data['id']);
        if (! $sub) {
            return ToolResult::error('Tarea no encontrada: ' . $data['id']);
        }

        $padre = $sub->padre;
        if (! $padre) {
            return ToolResult::error('La actividad o subactividad padre de la tarea no existe.');
        }

        if (! $this->user()->can('manage', $padre->project)) {
            return ToolResult::error('No tenés permiso para eliminar tareas en ese espacio.');
        }

        $key = $sub->key;
        $sub->delete();

        return ToolResult::json([
            'deleted' => true,
            'id'      => $data['id'],
            'key'     => $key,
            'message' => "Tarea {$key} eliminada.",
        ]);
    }
}
