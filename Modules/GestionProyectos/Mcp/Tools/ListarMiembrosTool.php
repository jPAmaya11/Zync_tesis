<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSpaceMember;

/**
 * Lista los miembros de un espacio con su rol (Blueprint §2.1).
 */
#[IsReadOnly]
class ListarMiembrosTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-miembros';
    }

    public function description(): string
    {
        return 'Lista los miembros de un espacio con su rol (propietario, administrador, ejecutor, aprobador, lector). '
            . 'Requiere acceso al espacio.';
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

        $miembros = GpSpaceMember::where('project_key', $data['project_key'])
            ->with('user')
            ->orderBy('role')
            ->get()
            ->map(fn ($m) => $m->toFrontend())
            ->values()
            ->all();

        return ToolResult::json(['miembros' => $miembros]);
    }
}
