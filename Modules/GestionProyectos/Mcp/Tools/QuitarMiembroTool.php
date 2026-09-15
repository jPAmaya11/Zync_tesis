<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSpaceMember;

/**
 * Quita un miembro de un espacio por su id de membresía. No se puede quitar al propietario.
 */
#[IsDestructive]
class QuitarMiembroTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'quitar-miembro';
    }

    public function description(): string
    {
        return 'Quita un miembro de un espacio por su id de membresía (de listar-miembros). Requiere ser '
            . 'administrador/propietario del espacio. No se puede quitar al propietario (transferí el rol primero). '
            . 'Operación destructiva: confirmá con el usuario antes de ejecutarla.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('member_id')->description('ID de la membresía (campo "id" de listar-miembros). Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'member_id' => ['required', 'integer'],
        ])->validate();

        $member = GpSpaceMember::find($data['member_id']);
        if (! $member) {
            return ToolResult::error('Miembro no encontrado: ' . $data['member_id']);
        }

        if (! $this->user()->can('manage', $member->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar miembros de ese espacio.');
        }

        if ($member->role === 'propietario') {
            return ToolResult::error('No se puede quitar al Propietario. Transferí el rol primero.');
        }

        $member->delete();

        return ToolResult::json([
            'deleted' => true,
            'member_id' => $data['member_id'],
            'message' => 'Miembro eliminado del espacio.',
        ]);
    }
}
