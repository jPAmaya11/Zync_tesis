<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Support\CriticalTransitionContext;

/**
 * Registra un comentario (solo texto) en el Historial de Actividades de una tarea.
 *
 * Es la EVIDENCIA que exige el flujo antes de mover a un estado crítico
 * (Finalizado / Reprogramado). Reusa la misma autorización (policy
 * registrarHistorial) y el mismo modelo que la web; el adjunto sigue por la web.
 */
class AgregarComentarioTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'agregar-comentario';
    }

    public function description(): string
    {
        return 'Agrega un comentario (solo texto) al Historial de Actividades de una tarea. '
            . 'ATENCIÓN: para mover a Finalizado, Reprogramado o Cancelado se requiere una evidencia ESPECÍFICA '
            . 'de esa transición. Pasá "para_estado" con el estado destino exacto (ej. "Cancelado") para que '
            . 'esta evidencia habilite ese cambio; un comentario sin para_estado NO habilita la transición. '
            . 'Flujo: agregar-comentario con para_estado=<estado destino>, luego actualizar-actividad con ese mismo status. '
            . 'Cada transición crítica exige su propia evidencia (la de una transición previa no sirve). '
            . 'Requiere permiso de escritura o de aprobación en el espacio.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('key')->description('Key de la tarea, ej. ERP-0123. Obligatorio.')->required();
        $schema->string('comment')->description('Texto del comentario / evidencia. Obligatorio.')->required();
        $schema->string('para_estado')->description('Estado destino exacto para el que esta evidencia habilita la transición (Finalizado / Reprogramado / Cancelado). Requerido si la evidencia es para un cambio de estado crítico.');
        $schema->boolean('for_critical_transition')->description('Marcá true si este comentario es la evidencia para un cambio de estado crítico inmediato (evita un correo duplicado). Por defecto false.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'key'                     => ['required', 'string'],
            'comment'                 => ['required', 'string', 'max:5000'],
            'para_estado'             => ['nullable', 'string', 'max:60'],
            'for_critical_transition' => ['nullable', 'boolean'],
        ])->validate();

        $proyecto = Proyecto::where('key', $data['key'])->first();
        if (! $proyecto) {
            return ToolResult::error('Tarea no encontrada: ' . $data['key']);
        }

        $user = $this->user();
        if (! $user->can('registrarHistorial', $proyecto->project)) {
            return ToolResult::error('No tenés permiso para comentar en esa tarea.');
        }

        // Si viene para_estado, la evidencia es para una transición crítica.
        $paraEstado  = $data['para_estado'] ?? null;
        $forCritical = (bool) ($data['for_critical_transition'] ?? false) || $paraEstado !== null;
        if ($forCritical) {
            CriticalTransitionContext::enter();
        }

        try {
            $entry = GpActivityHistory::create([
                'tarea_key'       => $proyecto->key,
                'user_id'         => $user->id,
                'comment'         => $data['comment'],
                'new_status'      => $paraEstado,
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
            ]);
        } finally {
            if ($forCritical) {
                CriticalTransitionContext::leave();
            }
        }

        return ToolResult::json($entry->load('user')->toFrontend());
    }
}
