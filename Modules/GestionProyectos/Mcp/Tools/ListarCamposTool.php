<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;

/**
 * Lista los campos personalizados de un espacio + los tipos de campo disponibles.
 */
#[IsReadOnly]
class ListarCamposTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-campos-personalizados';
    }

    public function description(): string
    {
        return 'Lista los campos personalizados de un espacio (id, nombre, tipo, opciones, si es predefinido) '
            . 'y los tipos de campo disponibles. Requiere acceso al espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
        ])->validate();

        if (! $this->canAccessProject($this->user(), $data['project_key'])) {
            return ToolResult::error('No tenés acceso a ese espacio.');
        }

        $svc = app(CustomFieldServiceInterface::class);

        return ToolResult::json([
            'campos'           => $svc->getCustomFields($data['project_key']),
            'tipos_disponibles' => array_column($svc->getAvailableFieldTypes(), 'value'),
        ]);
    }
}
