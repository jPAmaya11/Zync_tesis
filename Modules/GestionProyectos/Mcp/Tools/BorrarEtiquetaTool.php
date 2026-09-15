<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;

/**
 * Elimina una etiqueta de un espacio por su id.
 */
#[IsDestructive]
class BorrarEtiquetaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-etiqueta';
    }

    public function description(): string
    {
        return 'Elimina una etiqueta de un espacio por su id (de listar-etiquetas). Requiere ser '
            . 'administrador/propietario del espacio. Operación destructiva: confirmá con el usuario antes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('label_id')->description('ID de la etiqueta a eliminar (de listar-etiquetas). Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'label_id' => ['required', 'integer'],
        ])->validate();

        $label = GpLabel::find($data['label_id']);
        if (! $label) {
            return ToolResult::error('Etiqueta no encontrada: ' . $data['label_id']);
        }

        if (! $this->user()->can('manage', $label->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar etiquetas de ese espacio.');
        }

        $name = $label->name;
        $label->delete();
        Cache::forget('gp.projects');

        return ToolResult::json([
            'deleted'  => true,
            'label_id' => $data['label_id'],
            'message'  => "Etiqueta \"{$name}\" eliminada.",
        ]);
    }
}
