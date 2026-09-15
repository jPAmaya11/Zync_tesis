<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;
use Modules\GestionProyectos\Models\GpProject;

/**
 * Crea una etiqueta en un espacio (única por espacio).
 */
class CrearEtiquetaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-etiqueta';
    }

    public function description(): string
    {
        return 'Crea una etiqueta (label) en un espacio. Requiere ser administrador/propietario del espacio. '
            . 'El nombre debe ser único dentro del espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->string('name')->description('Nombre de la etiqueta. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
            'name'        => ['required', 'string', 'max:100'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar etiquetas de ese espacio.');
        }

        if (! GpProject::where('key', $data['project_key'])->exists()) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        if (GpLabel::forProject($data['project_key'])->where('name', $data['name'])->exists()) {
            return ToolResult::error('Ya existe una etiqueta con ese nombre en este espacio.');
        }

        $label = GpLabel::create(['project_key' => $data['project_key'], 'name' => $data['name']]);
        Cache::forget('gp.projects');

        return ToolResult::json(['id' => $label->id, 'name' => $label->name]);
    }
}
