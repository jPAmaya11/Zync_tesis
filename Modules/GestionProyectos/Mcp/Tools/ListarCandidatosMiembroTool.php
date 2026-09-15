<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

/**
 * Lista los usuarios elegibles para ser miembros de un espacio
 * (los que tienen el permiso gestion-proyectos.miembro o admin).
 *
 * Sólo accesible para administradores/propietarios de al menos un espacio,
 * o para administradores globales (BFLA: evita que lectores enumeren todos los usuarios).
 */
#[IsReadOnly]
class ListarCandidatosMiembroTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-candidatos-miembro';
    }

    public function description(): string
    {
        return 'Lista los usuarios que pueden ser agregados como miembros de un espacio '
            . '(tienen el permiso "gestion-proyectos.miembro"). Devuelve account_id, nombre y email; '
            . 'usá el account_id en agregar-miembro. Requiere ser administrador/propietario de al menos un espacio.';
    }

    public function handle(array $arguments): ToolResult
    {
        $user = $this->user();

        $isAdmin = $user->hasRole(['admin', 'super-admin', 'super_admin'])
                || $user->can('gestion-proyectos.admin');

        if (! $isAdmin) {
            // Debe ser administrador o propietario de al menos un espacio.
            $canManageAny = \Modules\GestionProyectos\Models\GpSpaceMember::where('user_id', $user->id)
                ->where('suspended', false)
                ->whereIn('role', ['propietario', 'administrador'])
                ->exists();

            if (! $canManageAny) {
                return ToolResult::error('Solo los administradores o propietarios de espacios pueden listar candidatos.');
            }
        }

        return ToolResult::json([
            'candidatos' => $this->service()->getSpaceMemberCandidates(),
        ]);
    }
}
