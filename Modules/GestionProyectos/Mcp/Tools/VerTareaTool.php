<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSubTarea;

#[IsReadOnly]
class VerTareaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'ver-tarea';
    }

    public function description(): string
    {
        return 'Devuelve el detalle de una tarea hoja por su id numérico o su key (ej. ERP-0001-3). '
            . 'IMPORTANTE: las tareas usan id numérico (int), NO key de texto. '
            . 'El id aparece en los resultados de listar-tareas y crear-tarea. '
            . 'La key tiene el formato {parent_key}-{N}.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('id')->description('ID numérico de la tarea (campo "id" en listar-tareas / crear-tarea).');
        $schema->string('key')->description('Key de la tarea, ej. ERP-0001-3 (alternativa al id).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'id'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'key' => ['sometimes', 'nullable', 'string', 'max:80'],
        ])->validate();

        if (empty($data['id']) && empty($data['key'])) {
            return ToolResult::error('Debés proveer id (int) o key (ej. ERP-0001-3) de la tarea.');
        }

        $sub = ! empty($data['id'])
            ? GpSubTarea::find((int) $data['id'])
            : GpSubTarea::where('key', $data['key'])->first();

        if (! $sub) {
            return ToolResult::error('Tarea no encontrada.');
        }

        $padre = $sub->padre;
        if (! $padre || ! $this->canAccessProject($this->user(), $padre->project)) {
            return ToolResult::error('No tenés acceso a esa tarea.');
        }

        $sub->load(['assignee', 'creator']);

        return ToolResult::json($sub->toFrontend());
    }
}
