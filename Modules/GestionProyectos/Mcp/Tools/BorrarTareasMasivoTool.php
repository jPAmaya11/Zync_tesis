<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

/**
 * Elimina (soft delete) varios issues a la vez (reusa ProyectoService::bulkDelete).
 * Cada tarea respeta la policy de borrado; las que fallan se devuelven en "failed".
 */
#[IsDestructive]
class BorrarTareasMasivoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-tareas-masivo';
    }

    public function description(): string
    {
        return 'Elimina (soft delete) varios issues a la vez por sus keys. Cada tarea respeta tu policy de borrado. '
            . 'Operación destructiva: confirmá con el usuario antes de ejecutarla. Devuelve éxitos y fallos.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->raw('keys', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Keys de las tareas a eliminar, ej. ["ERP-0001","ERP-0002"]. Obligatorio.']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'keys'   => ['required', 'array', 'min:1', 'max:200'],
            'keys.*' => ['string'],
        ])->validate();

        $result = $this->service()->bulkDelete($data['keys'], $this->user());

        return ToolResult::json($result);
    }
}
