<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

/**
 * Actualiza varios issues a la vez con los mismos campos (reusa ProyectoService::bulkUpdate).
 *
 * El cambio de estado (status) NO se permite en masa: el service lo ignora porque
 * los críticos exigen evidencia individual. Cada tarea respeta la policy de edición;
 * las que fallan se devuelven en "failed".
 */
class ActualizarTareasMasivoTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-tareas-masivo';
    }

    public function description(): string
    {
        return 'Actualiza varios issues a la vez aplicando los mismos campos. '
            . 'NO permite cambiar el estado (status) en masa: eso es individual por la regla de evidencia. '
            . 'Cada tarea respeta tu permiso de edición; devuelve las que se actualizaron y las que fallaron.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->raw('keys', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Keys de las tareas a actualizar, ej. ["ERP-0001","ERP-0002"]. Obligatorio.']);
        $schema->string('priority')->description('Nueva prioridad para todas.');
        $schema->string('issue_type')->description('Nuevo tipo de issue para todas.');
        $schema->string('assignee_account_id')->description('account_id del asignado (vacío para desasignar).');
        $schema->integer('team_id')->description('ID del equipo asignado (alternativo a assignee).');
        $schema->string('fecha_limite')->description('Fecha límite, YYYY-MM-DD.');
        $schema->raw('labels', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Etiquetas (reemplaza las actuales en cada tarea).']);
        $schema->raw('impacto', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Áreas de impacto.']);
        $schema->string('software')->description('Software relacionado.');
        $schema->string('entorno')->description('Entorno.');
        $schema->string('categoria')->description('Categoría a aplicar a todas (texto libre). Si no existe en el espacio, se da de alta sola.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $validated = Validator::make($arguments, [
            'keys'                => ['required', 'array', 'min:1', 'max:200'],
            'keys.*'              => ['string'],
            'priority'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'issue_type'          => ['sometimes', 'nullable', 'string', 'max:60'],
            'assignee_account_id' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'team_id'             => ['sometimes', 'nullable', 'integer', 'exists:gp_teams,id'],
            'fecha_limite'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'labels'              => ['sometimes', 'nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'impacto'             => ['sometimes', 'nullable', 'array'],
            'impacto.*'           => ['string', 'max:100'],
            'software'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'entorno'             => ['sometimes', 'nullable', 'string', 'max:100'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'           => ['sometimes', 'nullable', 'string', 'max:100'],
        ])->validate();

        $keys = $validated['keys'];
        unset($validated['keys']);
        $fields = $validated;

        if (array_key_exists('assignee_account_id', $fields)) {
            [$assigneeId, $assigneeErr] = $this->resolveAndValidateUserId($fields['assignee_account_id'], 'asignado');
            if ($assigneeErr) {
                return ToolResult::error($assigneeErr);
            }
            $fields['assignee_id'] = $assigneeId;
            unset($fields['assignee_account_id']);
        }

        if (empty($fields)) {
            return ToolResult::error('No se enviaron campos para actualizar.');
        }

        $result = $this->service()->bulkUpdate($keys, $fields, null, $this->user());

        return ToolResult::json($result);
    }
}
