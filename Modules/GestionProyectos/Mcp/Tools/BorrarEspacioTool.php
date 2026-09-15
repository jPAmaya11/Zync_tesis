<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;

/**
 * Elimina (soft delete) un espacio. Sólo propietario o administrador del espacio.
 */
#[IsDestructive]
class BorrarEspacioTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'borrar-espacio';
    }

    public function description(): string
    {
        return 'Elimina (soft delete) un espacio por su key. Sólo el propietario o administrador del espacio '
            . 'puede hacerlo. Operación destructiva y de alto impacto: confirmá con el usuario antes.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio a eliminar, ej. ERP. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('Sólo el propietario o administrador del espacio puede eliminarlo.');
        }

        $project = GpProject::where('key', $data['project_key'])->first();
        if (! $project) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        $name = $project->name;

        // La auditoría del borrado la registra GpProjectObserver::deleted() (capa de modelo),
        // cubriendo este path MCP y el de la interfaz web por igual. El usuario lo resuelve
        // Auth::id() porque el token MCP setea el usuario vía Auth::setUser.
        $project->delete();

        return ToolResult::json([
            'deleted'     => true,
            'project_key' => $data['project_key'],
            'message'     => "Espacio '{$name}' eliminado.",
        ]);
    }
}
