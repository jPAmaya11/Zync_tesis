<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\User\Models\User;

/**
 * Agrega un usuario como miembro de un espacio con un rol (Blueprint §2.1).
 *
 * Replica las reglas del controlador web: requiere permiso 'manage', el usuario
 * destino debe tener 'gestion-proyectos.miembro', y sólo puede haber un propietario.
 * Alta manual → team_synced = false (no atado a equipos).
 */
class AgregarMiembroTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'agregar-miembro';
    }

    public function description(): string
    {
        return 'Agrega un usuario como miembro de un espacio con un rol (propietario, administrador, ejecutor, '
            . 'aprobador, implementador, lector). Requiere ser administrador/propietario del espacio. El usuario debe tener el '
            . 'permiso "gestion-proyectos.miembro" (ver listar-candidatos-miembro). Sólo puede haber un propietario.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->string('account_id')->description('account_id del usuario a agregar (de listar-candidatos-miembro). Obligatorio.')->required();
        $schema->string('role')->description('Rol: propietario, administrador, ejecutor, aprobador, implementador o lector. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key' => ['required', 'string'],
            'account_id'  => ['required', 'string'],
            'role'        => ['required', 'string', 'in:propietario,administrador,ejecutor,aprobador,lector,implementador'],
        ])->validate();

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar miembros de ese espacio.');
        }

        if (! GpProject::where('key', $data['project_key'])->exists()) {
            return ToolResult::error('Espacio no encontrado: ' . $data['project_key']);
        }

        [$userId, $userErr] = $this->resolveAndValidateUserId($data['account_id'], 'usuario');
        if ($userErr) {
            return ToolResult::error($userErr);
        }
        $target = $userId ? User::find($userId) : null;
        if (! $target) {
            return ToolResult::error('Usuario no encontrado: ' . $data['account_id']);
        }

        if (! $target->can('gestion-proyectos.miembro') && ! $target->can('gestion-proyectos.admin')) {
            return ToolResult::error('El usuario no tiene el permiso "gestion-proyectos.miembro" requerido para ser miembro de un espacio.');
        }

        if ($data['role'] === 'propietario') {
            $existing = GpSpaceMember::where('project_key', $data['project_key'])
                ->where('role', 'propietario')
                ->first();
            if ($existing) {
                return ToolResult::error('Ya existe un Propietario en este espacio. Cambiá el rol del actual primero.');
            }
        }

        $member = GpSpaceMember::updateOrCreate(
            ['project_key' => $data['project_key'], 'user_id' => $userId],
            ['role' => $data['role'], 'team_synced' => false]
        );

        return ToolResult::json($member->load('user')->toFrontend());
    }
}
