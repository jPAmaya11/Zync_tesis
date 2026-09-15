<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Crea una Subactividad: un Proyecto anidado dentro de una Actividad (parent_key).
 *
 * Tiene los mismos campos y flujo que una Actividad. La regla de fecha (inicio >= inicio
 * del padre) la impone el modelo; el espacio se hereda del padre.
 */
class CrearSubactividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-subactividad';
    }

    public function description(): string
    {
        return 'Crea una subactividad anidada dentro de una actividad. Tiene los mismos campos que una actividad '
            . 'y sigue el mismo flujo SCRUM. Requiere parent_key (key de la actividad padre) y start_date; la fecha '
            . 'de inicio NO puede ser anterior a la del padre. Hereda el espacio del padre.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('parent_key')->description('Key de la ACTIVIDAD padre (top-level), ej. ERP-0123. Obligatorio.')->required();
        $schema->string('summary')->description('Título/asunto. Obligatorio.')->required();
        $schema->string('start_date')->description('Fecha de inicio YYYY-MM-DD (no anterior a la del padre). Obligatorio.')->required();
        $schema->string('priority')->description('Prioridad: Baja, Media, Alta.');
        $schema->string('issue_type')->description('Tipo de issue (ver listar-catalogos).');
        $schema->string('status')->description('Estado inicial (por defecto Pendiente).');
        $schema->string('description')->description('Descripción detallada.');
        $schema->integer('dias_estimados')->description('Días estimados (con start_date calcula la fecha límite).');
        $schema->string('fecha_entrega')->description('Fecha Subida Stage YYYY-MM-DD.');
        $schema->string('fecha_aprobacion')->description('Fecha Subida Producción YYYY-MM-DD.');
        $schema->raw('labels', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Etiquetas.']);
        $schema->raw('impacto', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Áreas de impacto.']);
        $schema->string('software')->description('Software relacionado.');
        $schema->string('entorno')->description('Entorno.');
        $schema->string('solicitado_por')->description('Quién lo solicitó.');
        $schema->string('categoria')->description('Categoría de la subactividad (texto libre). Si no existe en el espacio, se da de alta sola.');
        $schema->string('assignee_account_id')->description('account_id del asignado (de listar-usuarios-asignables).');
        $schema->string('reporter_account_id')->description('account_id del reportador (por defecto, vos).');
        $schema->integer('team_id')->description('ID del equipo (alternativo a assignee).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $validated = Validator::make($arguments, [
            'parent_key'          => ['required', 'string'],
            'summary'             => ['required', 'string', 'max:500'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'issue_type'          => ['nullable', 'string', 'max:60'],
            'description'         => ['nullable', 'string', 'max:10000'],
            'dias_estimados'      => ['nullable', 'integer', 'min:1', 'max:9999'],
            'fecha_entrega'       => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'    => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'impacto'             => ['nullable', 'array'],
            'impacto.*'           => ['string', 'max:100'],
            'software'            => ['nullable', 'string', 'max:100'],
            'entorno'             => ['nullable', 'string', 'max:100'],
            'solicitado_por'      => ['nullable', 'string', 'max:255'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'           => ['nullable', 'string', 'max:100'],
            'assignee_account_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'reporter_account_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'team_id'             => ['nullable', 'integer', 'exists:gp_teams,id'],
        ])->validate();

        $padre = Proyecto::where('key', $validated['parent_key'])->first();
        if (! $padre) {
            return ToolResult::error('Actividad padre no encontrada: ' . $validated['parent_key']);
        }
        if (! empty($padre->parent_key)) {
            return ToolResult::error('Una subactividad solo puede colgar de una ACTIVIDAD (no de otra subactividad).');
        }

        $user = $this->user();
        if (! $user->can('crear', $padre->project)) {
            return ToolResult::error('No tenés permiso para crear en ese espacio.');
        }

        [$assigneeId, $assigneeErr] = $this->resolveAndValidateUserId($validated['assignee_account_id'] ?? null, 'asignado');
        if ($assigneeErr) {
            return ToolResult::error($assigneeErr);
        }

        $reporterAccountId = $validated['reporter_account_id'] ?? null;
        [$reporterId, $reporterErr] = empty($reporterAccountId)
            ? [$user->id, null]
            : $this->resolveAndValidateUserId($reporterAccountId, 'reportador');
        if ($reporterErr) {
            return ToolResult::error($reporterErr);
        }

        $data = $validated;
        $data['project_key'] = $padre->project;
        $data['parent_key']  = $padre->key;
        $data['assignee_id'] = $assigneeId;
        $data['creator_id']  = $user->id;
        $data['reporter_id'] = $reporterId;
        unset($data['assignee_account_id'], $data['reporter_account_id']);

        try {
            $sub = $this->service()->create($data);

            return ToolResult::json($this->toApi($this->loadForDto($sub)));
        } catch (ValidationException $e) {
            return ToolResult::error(implode(' ', $e->validator->errors()->all()));
        } catch (GestionProyectosException $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
