<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

#[IsReadOnly]
class ListarUsuariosTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-usuarios-asignables';
    }

    public function description(): string
    {
        return 'Lista usuarios asignables (account_id, nombre, email). Pasá "project" (key del espacio) '
            . 'para limitar a sus miembros y "search" para filtrar por nombre/email. '
            . 'El account_id se usa en assignee_account_id / reporter_account_id.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project')->description('Key del espacio para limitar a sus miembros (opcional).');
        $schema->string('search')->description('Filtro por nombre o email (opcional).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $project = (string) ($arguments['project'] ?? '');
        $search  = (string) ($arguments['search'] ?? '');
        $user    = $this->user();

        $isAdmin = $user->hasRole(['admin', 'super-admin', 'super_admin'])
                || $user->can('gestion-proyectos.admin');

        if ($project !== '') {
            // Con espacio explícito: verificar acceso.
            if (! $this->canAccessProject($user, $project)) {
                return ToolResult::error('No tenés acceso a ese espacio.');
            }
        } elseif (! $isAdmin) {
            // Sin espacio: el usuario normal solo puede ver asignables de sus propios espacios.
            // Tomamos el primer espacio del que es miembro como scope implícito,
            // o devolvemos la unión si es miembro de varios (usando project vacío + sub-filtro).
            // Estrategia: pasar una lista de project_keys al servicio via el primer espacio propio
            // — simplificado: dejamos que getAssignableUsers filtre por membresía global del user.
            // Nota: getAssignableUsers sin project_key ya filtra por gestion-proyectos.miembro.
            // El riesgo real es enumerar emails de todo el módulo. Para acotarlo, exigimos que
            // la consulta sin project_key solo la hagan admins; el user normal debe pasar project.
            $myProjectKeys = \Modules\GestionProyectos\Models\GpSpaceMember::where('user_id', $user->id)
                ->where('suspended', false)
                ->pluck('project_key')
                ->all();

            if (empty($myProjectKeys)) {
                return ToolResult::json(['usuarios' => []]);
            }

            // Retorna usuarios asignables de SUS espacios (unión de miembros de todos sus espacios).
            return ToolResult::json([
                'usuarios' => $this->service()->getAssignableUsersForSpaces($myProjectKeys, $search),
            ]);
        }

        return ToolResult::json([
            'usuarios' => $this->service()->getAssignableUsers($project, $search),
        ]);
    }
}
