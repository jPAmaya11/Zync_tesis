<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

class CrearActividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-actividad';
    }

    public function description(): string
    {
        return 'Crea una tarea/issue SCRUM en un espacio. Requiere project_key y summary. '
            . 'Respeta el permiso de creación del usuario en ese espacio. '
            . 'Para asignar, usá assignee_account_id (de listar-usuarios-asignables).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio, ej. ERP. Obligatorio.')->required();
        $schema->string('summary')->description('Título/asunto de la tarea. Obligatorio.')->required();
        $schema->string('priority')->description('Prioridad (ver listar-catalogos): Baja, Media, Alta, Crítica.');
        $schema->string('issue_type')->description('Tipo de issue (ver listar-catalogos): Tarea, Historia, Error, Mejora.');
        $schema->string('status')->description('Estado inicial (ver listar-catalogos). Por defecto Pendiente.');
        $schema->string('description')->description('Descripción detallada.');
        $schema->string('start_date')->description('Fecha de inicio, YYYY-MM-DD.');
        $schema->integer('dias_estimados')->description('Días estimados (junto con start_date calcula la fecha límite).');
        $schema->string('fecha_entrega')->description('Fecha Subida Stage, YYYY-MM-DD.');
        $schema->string('fecha_aprobacion')->description('Fecha Subida Producción, YYYY-MM-DD.');
        $schema->raw('labels', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Etiquetas.']);
        $schema->raw('impacto', ['type' => 'array', 'items' => ['type' => 'string', 'enum' => config('gestion-proyectos.impacto_values', [])], 'description' => 'Nivel(es) de impacto. SOLO: Crítico, Alto, Medio, Bajo, Sin impacto.']);
        $schema->string('software')->description('Software relacionado.');
        $schema->string('entorno')->description('Entorno (ej. Produccion, Stage).');
        $schema->string('solicitado_por')->description('Quién lo solicitó.');
        $schema->string('categoria')->description('Categoría de la actividad (texto libre). Si no existe en el espacio, se da de alta sola.');
        $schema->string('assignee_account_id')->description('account_id del asignado (de listar-usuarios-asignables).');
        $schema->string('reporter_account_id')->description('account_id del reportador. Por defecto, el usuario del token.');
        $schema->integer('team_id')->description('ID del equipo asignado (alternativo a assignee).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $validated = Validator::make($arguments, [
            'project_key'         => ['required', 'string', 'exists:gp_projects,key'],
            'summary'             => ['required', 'string', 'max:500'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'issue_type'          => ['nullable', 'string', 'max:60'],
            'description'         => ['nullable', 'string', 'max:10000'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'dias_estimados'      => ['nullable', 'integer', 'min:1', 'max:9999'],
            'fecha_entrega'       => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'    => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'impacto'             => ['nullable', 'array'],
            'impacto.*'           => ['string', Rule::in(config('gestion-proyectos.impacto_values', []))],
            'software'            => ['nullable', 'string', 'max:100'],
            'entorno'             => ['nullable', 'string', 'max:100'],
            'solicitado_por'      => ['nullable', 'string', 'max:255'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'           => ['nullable', 'string', 'max:100'],
            'assignee_account_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'reporter_account_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'team_id'             => ['nullable', 'integer', 'exists:gp_teams,id'],
        ])->validate();

        $user = $this->user();
        if (! $user->can('crear', $validated['project_key'])) {
            return ToolResult::error('No tenés permiso para crear tareas en ese espacio.');
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
        $data['assignee_id'] = $assigneeId;
        $data['creator_id']  = $user->id;
        $data['reporter_id'] = $reporterId;
        unset($data['assignee_account_id'], $data['reporter_account_id']);

        try {
            $proyecto = $this->service()->create($data);

            return ToolResult::json($this->toApi($this->loadForDto($proyecto)));
        } catch (GestionProyectosException $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
