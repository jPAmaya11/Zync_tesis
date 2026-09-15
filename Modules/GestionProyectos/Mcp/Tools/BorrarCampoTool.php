<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpCustomField;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

/**
 * Elimina un campo personalizado por su id. Los campos predefinidos no se pueden borrar.
 */
#[IsDestructive]
class BorrarCampoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-campo-personalizado';
    }

    public function description(): string
    {
        return 'Elimina un campo personalizado por su id (de listar-campos-personalizados). Requiere ser '
            . 'administrador/propietario del espacio. Los campos predefinidos (is_default) no se pueden borrar. '
            . 'Operación destructiva: confirmá con el usuario antes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('field_id')->description('ID del campo a eliminar (de listar-campos-personalizados). Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'field_id' => ['required', 'integer'],
        ])->validate();

        $field = GpCustomField::find($data['field_id']);
        if (! $field) {
            return ToolResult::error('Campo no encontrado: ' . $data['field_id']);
        }

        if (! $this->user()->can('manage', $field->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar campos de ese espacio.');
        }

        if ($field->is_default) {
            return ToolResult::error('Este es un campo predefinido del proyecto y no puede eliminarse.');
        }

        $name = $field->name;
        app(CustomFieldServiceInterface::class)->deleteCustomField($field->id);

        return ToolResult::json([
            'deleted'  => true,
            'field_id' => $data['field_id'],
            'message'  => "Campo \"{$name}\" eliminado.",
        ]);
    }
}
