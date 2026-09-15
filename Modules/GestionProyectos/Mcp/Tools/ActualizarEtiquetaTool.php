<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;

/**
 * Renombra una etiqueta de un espacio por su id.
 */
class ActualizarEtiquetaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-etiqueta';
    }

    public function description(): string
    {
        return 'Renombra una etiqueta de un espacio por su id (de listar-etiquetas). Requiere ser '
            . 'administrador/propietario del espacio. El nuevo nombre debe ser único dentro del espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('label_id')->description('ID de la etiqueta (de listar-etiquetas). Obligatorio.')->required();
        $schema->string('name')->description('Nuevo nombre. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'label_id' => ['required', 'integer'],
            'name'     => ['required', 'string', 'max:100'],
        ])->validate();

        $label = GpLabel::find($data['label_id']);
        if (! $label) {
            return ToolResult::error('Etiqueta no encontrada: ' . $data['label_id']);
        }

        if (! $this->user()->can('manage', $label->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar etiquetas de ese espacio.');
        }

        $dup = GpLabel::forProject($label->project_key)
            ->where('name', $data['name'])
            ->where('id', '!=', $label->id)
            ->exists();
        if ($dup) {
            return ToolResult::error('Ya existe una etiqueta con ese nombre en este espacio.');
        }

        $label->update(['name' => $data['name']]);
        Cache::forget('gp.projects');

        return ToolResult::json(['id' => $label->id, 'name' => $label->name]);
    }
}
