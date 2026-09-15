<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpCustomField;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

/**
 * Actualiza un campo personalizado por su id. En campos predefinidos sólo se permite name/order.
 */
class ActualizarCampoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-campo-personalizado';
    }

    public function description(): string
    {
        return 'Actualiza un campo personalizado por su id (de listar-campos-personalizados). Requiere ser '
            . 'administrador/propietario del espacio. En campos predefinidos (is_default) sólo se permite '
            . 'cambiar nombre y orden.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('field_id')->description('ID del campo (de listar-campos-personalizados). Obligatorio.')->required();
        $schema->string('name')->description('Nuevo nombre.');
        $schema->string('type')->description('Nuevo tipo (no aplica a campos predefinidos).');
        $schema->raw('options', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Nuevas opciones (dropdown/select).']);
        $schema->integer('order')->description('Nuevo orden.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'field_id'  => ['required', 'integer'],
            'name'      => ['sometimes', 'string', 'max:100'],
            'type'      => ['sometimes', 'string', 'in:text,paragraph,timestamp,dropdown,date,number,labels,checkbox,people,url,select'],
            'options'   => ['sometimes', 'nullable', 'array'],
            'options.*' => ['string', 'max:100'],
            'order'     => ['sometimes', 'integer', 'min:0'],
        ])->validate();

        $field = GpCustomField::find($data['field_id']);
        if (! $field) {
            return ToolResult::error('Campo no encontrado: ' . $data['field_id']);
        }

        if (! $this->user()->can('manage', $field->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar campos de ese espacio.');
        }

        unset($data['field_id']);

        // En campos predefinidos sólo se permite renombrar / reordenar.
        if ($field->is_default) {
            $data = array_intersect_key($data, array_flip(['name', 'order']));
        }

        if (empty($data)) {
            return ToolResult::error('No se enviaron campos para actualizar.');
        }

        $updated = app(CustomFieldServiceInterface::class)->updateCustomField($field->id, $data);

        return ToolResult::json($updated->toFrontend());
    }
}
