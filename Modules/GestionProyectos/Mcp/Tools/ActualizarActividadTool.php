<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;

class ActualizarActividadTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-actividad';
    }

    public function description(): string
    {
        return 'Actualiza campos de una tarea/issue por su key. Enviá sólo los campos a cambiar. '
            . 'Reglas de estado: Finalizado/Reprogramado/Cancelado solo los aplican Aprobadores, '
            . 'Administradores de espacio o Admins globales. Los tres exigen un comentario previo '
            . 'en el Historial (el adjunto es opcional). Finalizado es terminal. '
            . 'Reprogramado requiere fecha_reprogramacion. Si una regla bloquea, el error explica el motivo.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Key de la tarea, ej. ERP-0123. Obligatorio.')->required();
        $schema->string('summary')->description('Nuevo título/asunto.');
        $schema->string('status')->description('Nuevo estado (ver listar-catalogos).');
        $schema->string('priority')->description('Nueva prioridad.');
        $schema->string('issue_type')->description('Nuevo tipo de issue.');
        $schema->string('description')->description('Nueva descripción.');
        $schema->string('start_date')->description('Fecha de inicio, YYYY-MM-DD.');
        $schema->integer('dias_estimados')->description('Días estimados.');
        $schema->string('fecha_limite')->description('Fecha límite, YYYY-MM-DD.');
        $schema->string('fecha_entrega')->description('Fecha Subida Stage, YYYY-MM-DD.');
        $schema->string('fecha_aprobacion')->description('Fecha Subida Producción, YYYY-MM-DD.');
        $schema->string('fecha_reprogramacion')->description('Fecha de reprogramación, YYYY-MM-DD (requerida si pasás a Reprogramado).');
        $schema->raw('labels', ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Etiquetas (reemplaza las actuales).']);
        $schema->raw('impacto', ['type' => 'array', 'items' => ['type' => 'string', 'enum' => config('gestion-proyectos.impacto_values', [])], 'description' => 'Nivel(es) de impacto. SOLO: Crítico, Alto, Medio, Bajo, Sin impacto.']);
        $schema->string('software')->description('Software relacionado.');
        $schema->string('entorno')->description('Entorno.');
        $schema->string('solicitado_por')->description('Quién lo solicitó.');
        $schema->string('categoria')->description('Categoría de la actividad (texto libre). Si no existe en el espacio, se da de alta sola.');
        $schema->string('assignee_account_id')->description('account_id del asignado (vacío para desasignar).');
        $schema->string('reporter_account_id')->description('account_id del reportador.');
        $schema->integer('team_id')->description('ID del equipo (alternativo a assignee).');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $validated = Validator::make($arguments, [
            'key'                  => ['required', 'string'],
            'summary'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'priority'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'issue_type'           => ['sometimes', 'nullable', 'string', 'max:60'],
            'description'          => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_date'           => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'dias_estimados'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
            'fecha_limite'         => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_entrega'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'     => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_reprogramacion' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'labels'               => ['sometimes', 'nullable', 'array'],
            'labels.*'             => ['string', 'max:100'],
            'impacto'              => ['sometimes', 'nullable', 'array'],
            'impacto.*'            => ['string', Rule::in(config('gestion-proyectos.impacto_values', []))],
            'software'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'entorno'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'solicitado_por'       => ['sometimes', 'nullable', 'string', 'max:255'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'assignee_account_id'  => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'reporter_account_id'  => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'team_id'              => ['sometimes', 'nullable', 'integer', 'exists:gp_teams,id'],
        ])->validate();

        $key = $validated['key'];
        unset($validated['key']);
        $fields = $validated;

        $accountIdMap = ['assignee_account_id' => ['assignee_id', 'asignado'], 'reporter_account_id' => ['reporter_id', 'reportador']];
        foreach ($accountIdMap as $from => [$to, $label]) {
            if (array_key_exists($from, $fields)) {
                [$resolvedId, $err] = $this->resolveAndValidateUserId($fields[$from], $label);
                if ($err) {
                    return ToolResult::error($err);
                }
                $fields[$to] = $resolvedId;
                unset($fields[$from]);
            }
        }

        if (empty($fields)) {
            return ToolResult::error('No se enviaron campos para actualizar.');
        }

        try {
            $proyecto = $this->service()->update($key, $fields, $this->user());

            return ToolResult::json($this->toApi($this->loadForDto($proyecto)));
        } catch (AuthorizationException $e) {
            return ToolResult::error('Sin permiso: ' . $e->getMessage());
        } catch (GestionProyectosException $e) {
            return ToolResult::error($e->getMessage());
        } catch (ModelNotFoundException) {
            return ToolResult::error('Tarea no encontrada: ' . $key);
        }
    }
}
