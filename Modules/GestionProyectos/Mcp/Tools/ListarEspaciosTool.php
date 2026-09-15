<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

#[IsReadOnly]
class ListarEspaciosTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-espacios';
    }

    public function description(): string
    {
        return 'Lista los espacios (proyectos SCRUM) a los que el usuario del token tiene acceso. '
            . 'Usá el "key" devuelto como project_key en las demás operaciones.';
    }

    public function handle(array $arguments): ToolResult
    {
        $user = $this->user();

        $espacios = collect($this->service()->getProjects())
            ->filter(fn ($p) => isset($p['key']) && $this->canAccessProject($user, $p['key']))
            ->map(fn ($p) => [
                'key'  => $p['key'],
                'name' => $p['name'] ?? $p['key'],
            ])
            ->values()
            ->all();

        return ToolResult::json(['espacios' => $espacios]);
    }
}
