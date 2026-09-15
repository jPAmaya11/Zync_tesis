<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

#[IsReadOnly]
class ListarActividadesTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'listar-actividades';
    }

    public function description(): string
    {
        return 'Lista (paginado) las tareas/issues SCRUM de un espacio. Requiere "project" (key del espacio, '
            . 'ej. ERP). Permite filtrar por estados, prioridades, etiquetas y texto.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->string('search')->description('Texto a buscar en título/clave.');
        $schema->raw('statuses', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Filtrar por estados.']);
        $schema->raw('priorities', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Filtrar por prioridades.']);
        $schema->raw('labels', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Filtrar por etiquetas.']);
        $schema->string('order_by')->description('Orden, ej. "created_at DESC" o "fecha_limite ASC".');
        $schema->integer('page')->description('Página (default 1).');
        $schema->integer('per_page')->description('Items por página (default 50, máx 200).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project'    => ['required', 'string'],
            'search'     => ['nullable', 'string'],
            'statuses'   => ['nullable', 'array'],
            'priorities' => ['nullable', 'array'],
            'labels'     => ['nullable', 'array'],
            'order_by'   => ['nullable', 'string'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:200'],
        ])->validate();

        $user = $this->user();
        if (! $this->canAccessProject($user, $data['project'])) {
            return ToolResult::error('No tenés acceso a ese espacio.');
        }

        $page    = max(1, (int) ($data['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($data['per_page'] ?? 50)));

        $result = $this->service()->getPaginated([
            'project'    => $data['project'],
            'statuses'   => $data['statuses'] ?? [],
            'priorities' => $data['priorities'] ?? [],
            'labels'     => $data['labels'] ?? [],
            'search'     => $data['search'] ?? '',
            'order_by'   => $data['order_by'] ?? 'created_at DESC',
        ], $page, $perPage);

        return ToolResult::json([
            'data' => array_map(fn ($p) => $this->toApi($p), $result['data']),
            'meta' => [
                'total'        => $result['total'] ?? null,
                'per_page'     => $perPage,
                'current_page' => $result['current_page'] ?? $page,
                'last_page'    => $result['last_page'] ?? null,
            ],
        ]);
    }
}
