<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSpaceMember;

/**
 * Cambia el rol de un miembro de espacio por su id de membresía.
 */
class CambiarRolMiembroTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'cambiar-rol-miembro';
    }

    public function description(): string
    {
        return 'Cambia el rol de un miembro de un espacio por su id de membresía (de listar-miembros). '
            . 'Requiere ser administrador/propietario del espacio. Sólo puede haber un propietario.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('member_id')->description('ID de la membresía (campo "id" de listar-miembros). Obligatorio.')->required();
        $schema->string('role')->description('Nuevo rol: propietario, administrador, ejecutor, aprobador, implementador o lector. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'member_id' => ['required', 'integer'],
            'role'      => ['required', 'string', 'in:propietario,administrador,ejecutor,aprobador,lector,implementador'],
        ])->validate();

        $member = GpSpaceMember::find($data['member_id']);
        if (! $member) {
            return ToolResult::error('Miembro no encontrado: ' . $data['member_id']);
        }

        if (! $this->user()->can('manage', $member->project_key)) {
            return ToolResult::error('No tenés permiso para gestionar miembros de ese espacio.');
        }

        if ($data['role'] === 'propietario') {
            $existing = GpSpaceMember::where('project_key', $member->project_key)
                ->where('role', 'propietario')
                ->where('id', '!=', $member->id)
                ->first();
            if ($existing) {
                return ToolResult::error('Ya existe un Propietario. Cambiá el rol del actual Propietario primero.');
            }
        }

        $member->role = $data['role'];
        $member->save();

        return ToolResult::json($member->load('user')->toFrontend());
    }
}
