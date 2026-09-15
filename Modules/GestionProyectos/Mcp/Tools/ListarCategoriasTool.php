<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceCategory;
use Modules\GestionProyectos\Models\GpSpaceMember;

/**
 * Lista las categorías de espacio (id, name), acotadas a los espacios VISIBLES
 * para el usuario (punto crítico 1: el scoping por espacio también aplica a los
 * catálogos). El admin global sigue viendo el catálogo completo.
 */
#[IsReadOnly]
class ListarCategoriasTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-categorias';
    }

    public function description(): string
    {
        return 'Lista las categorías de espacio disponibles (id y nombre). Se usan para agrupar/clasificar espacios. '
            . 'Devuelve solo las categorías de los espacios a los que tenés acceso.';
    }

    public function handle(array $arguments): ToolResult
    {
        $user = $this->user();

        if (! $user->can('gestion-proyectos.ver')) {
            return ToolResult::error('No tenés acceso al módulo.');
        }

        $query = GpSpaceCategory::orderBy('name');

        // visibleProjectKeys() devuelve null para el admin global (= sin filtrar).
        $visibles = GpSpaceMember::visibleProjectKeys($user);

        if ($visibles !== null) {
            $query->whereIn('id', GpProject::whereIn('key', $visibles)
                ->whereNotNull('space_category_id')
                ->distinct()
                ->pluck('space_category_id'));
        }

        $categorias = $query->get(['id', 'name'])->values()->all();

        return ToolResult::json(['categorias' => $categorias]);
    }
}
