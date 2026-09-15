<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSubTarea;

/**
 * Actualiza una tarea por su id. Sólo cambia los campos enviados.
 */
class ActualizarTareaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'actualizar-tarea';
    }

    public function description(): string
    {
        return 'Actualiza una tarea por su id (numérico, de listar-tareas). Enviá sólo los campos a cambiar. '
            . 'status: Pendiente o Finalizado; priority: Alta, Media o Baja. '
            . 'assignee_account_id vacío para desasignar.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('id')->description('ID numérico de la tarea (de listar-tareas). Obligatorio.')->required();
        $schema->string('summary')->description('Nuevo título.');
        $schema->string('description')->description('Nueva descripción.');
        $schema->string('observacion')->description('Nueva observación.');
        $schema->string('assignee_account_id')->description('account_id del asignado (vacío para desasignar).');
        $schema->string('status')->description('Estado: Pendiente o Finalizado.');
        $schema->string('priority')->description('Prioridad: Alta, Media o Baja.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $validated = Validator::make($arguments, [
            'id'                  => ['required', 'integer'],
            'summary'             => ['sometimes', 'string', 'max:500'],
            'description'         => ['sometimes', 'nullable', 'string', 'max:5000'],
            'observacion'         => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assignee_account_id' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'status'              => ['sometimes', 'string', 'in:Pendiente,Finalizado'],
            'priority'            => ['sometimes', 'string', 'in:Alta,Media,Baja'],
        ])->validate();

        $sub = GpSubTarea::find($validated['id']);
        if (! $sub) {
            return ToolResult::error('Tarea no encontrada: ' . $validated['id']);
        }

        $padre = $sub->padre;
        if (! $padre) {
            return ToolResult::error('La actividad o subactividad padre de la tarea no existe.');
        }

        $user = $this->user();
        // registrarHistorial cubre canWrite (ejecutor) Y canApprove (aprobador),
        // igual que Policy::update() en actividades. Así el aprobador puede marcar
        // tareas como Finalizado, coherente con su rol en el flujo SCRUM.
        if (! $user->can('registrarHistorial', $padre->project)) {
            return ToolResult::error('No tenés permiso para editar tareas en ese espacio.');
        }

        unset($validated['id']);
        $fields = $validated;

        // La restricción del Aprobador (solo estado) vive en el hook de GpSubTarea, que
        // cubre web y MCP por igual y rechaza con un error explícito. Antes se filtraba
        // acá en silencio: el llamador recibía éxito y no se enteraba de lo descartado.

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

        try {
            $sub->update($fields);
        } catch (\Modules\GestionProyectos\Exceptions\GestionProyectosException $e) {
            return ToolResult::error($e->getMessage());
        }

        return ToolResult::json($sub->load(['assignee', 'creator'])->toFrontend());
    }
}
