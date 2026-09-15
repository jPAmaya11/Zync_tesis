<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\Proyecto;

#[IsReadOnly]
class VerActividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'ver-actividad';
    }

    public function description(): string
    {
        return 'Devuelve el detalle completo de una tarea/issue SCRUM por su key (ej. ERP-0123).';
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
            return ToolResult::error('Tarea no encontrada.');
        }
        if (! $this->canAccessProject($this->user(), $proyecto->project)) {
            return ToolResult::error('No tenés acceso a esa tarea.');
        }

        return ToolResult::json($this->toApi($this->loadForDto($proyecto)));
    }
}
