<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

#[IsDestructive]
class BorrarActividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-actividad';
    }

    public function description(): string
    {
        return 'Elimina (soft delete) una tarea/issue por su key. Respeta la policy de borrado del usuario. '
            . 'Operación destructiva: confirmá con el usuario antes de ejecutarla.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Key de la tarea a eliminar, ej. ERP-0123. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'key' => ['required', 'string'],
        ])->validate();

        try {
            $this->service()->delete($data['key'], $this->user());

            return ToolResult::json([
                'deleted' => true,
                'key'     => $data['key'],
                'message' => "Tarea {$data['key']} eliminada.",
            ]);
        } catch (AuthorizationException $e) {
            return ToolResult::error('Sin permiso: ' . $e->getMessage());
        } catch (ModelNotFoundException) {
            return ToolResult::error('Tarea no encontrada: ' . $data['key']);
        }
    }
}
