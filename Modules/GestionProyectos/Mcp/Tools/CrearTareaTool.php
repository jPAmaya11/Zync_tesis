<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpSubTarea;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Crea una tarea dentro de una actividad o subactividad padre (Blueprint §2.5).
 *
 * Replica la lógica del controlador web (autorización 'crear', key autogenerada
 * en transacción) sin tocar el controlador.
 */
class CrearTareaTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'crear-tarea';
    }

    public function description(): string
    {
        return 'Crea una tarea dentro de una actividad o subactividad padre. Requiere permiso de creación en el espacio. '
            . 'status: Pendiente o Finalizado (por defecto Pendiente); priority: Alta, Media o Baja. '
            . 'Para asignar usá assignee_account_id (de listar-usuarios-asignables).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('parent_key')->description('Key de la actividad o subactividad padre, ej. ERP-0123. Obligatorio.')->required();
        $schema->string('summary')->description('Título de la tarea. Obligatorio.')->required();
        $schema->string('description')->description('Descripción detallada.');
        $schema->string('observacion')->description('Observación.');
        $schema->string('assignee_account_id')->description('account_id del asignado (de listar-usuarios-asignables).');
        $schema->string('status')->description('Estado: Pendiente o Finalizado. Por defecto Pendiente.');
        $schema->string('priority')->description('Prioridad: Alta, Media o Baja.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'parent_key'          => ['required', 'string'],
            'summary'             => ['required', 'string', 'max:500'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'observacion'         => ['nullable', 'string', 'max:2000'],
            'assignee_account_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'status'              => ['nullable', 'string', 'in:Pendiente,Finalizado'],
            'priority'            => ['nullable', 'string', 'in:Alta,Media,Baja'],
        ])->validate();

        $padre = Proyecto::where('key', $data['parent_key'])->first();
        if (! $padre) {
            return ToolResult::error('Tarea padre no encontrada: ' . $data['parent_key']);
        }

        $user = $this->user();
        if (! $user->can('crear', $padre->project)) {
            return ToolResult::error('No tenés permiso para crear tareas en ese espacio.');
        }

        [$assigneeId, $assigneeErr] = $this->resolveAndValidateUserId($data['assignee_account_id'] ?? null, 'asignado');
        if ($assigneeErr) {
            return ToolResult::error($assigneeErr);
        }

        $sub = DB::transaction(fn () => GpSubTarea::create([
            'parent_key'    => $padre->key,
            'summary'       => $data['summary'],
            'description'   => $data['description'] ?? null,
            'observacion'   => $data['observacion'] ?? null,
            'assignee_id'   => $assigneeId,
            'creator_id'    => $user->id,
            'status'        => $data['status'] ?? 'Pendiente',
            'priority'      => $data['priority'] ?? null,
        ]));

        return ToolResult::json($sub->load(['assignee', 'creator'])->toFrontend());
    }
}
