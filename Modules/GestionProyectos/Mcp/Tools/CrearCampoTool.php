<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

/**
 * Crea un campo personalizado en un espacio. Requiere 'manage'.
 */
class CrearCampoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-campo-personalizado';
    }

    public function description(): string
    {
        return 'Crea un campo personalizado en un espacio. Requiere ser administrador/propietario del espacio. '
            . 'type: text, paragraph, timestamp, dropdown, date, number, labels, checkbox, people, url o select. '
            . 'Para dropdown/select pasá "options".';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->string('name')->description('Nombre del campo. Obligatorio.')->required();
        $schema->string('type')->description('Tipo: text, paragraph, timestamp, dropdown, date, number, labels, checkbox, people, url, select. Obligatorio.')->required();
        $schema->raw('options', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Opciones (para dropdown/select).']);
        $schema->integer('order')->description('Orden de visualización (opcional).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', 'string', 'in:text,paragraph,timestamp,dropdown,date,number,labels,checkbox,people,url,select'],
            'options'     => ['nullable', 'array'],
            'options.*'   => ['string', 'max:100'],
            'order'       => ['nullable', 'integer', 'min:0'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar campos de ese espacio.');
        }

        if (! GpProject::where('key', $data['project_key'])->exists()) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        $field = app(CustomFieldServiceInterface::class)->addCustomField($data['project_key'], $data);

        return ToolResult::json($field->toFrontend());
    }
}
