<?php

namespace Modules\GestionProyectos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpMailLog;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;

class SlaCheckCommand extends Command
{
    protected $signature = 'gp:mail:sla-check
        {--scrum-hours=24 : Ventana en horas para alertar tareas SCRUM por vencer}';

    protected $description = 'Notifica por correo tareas cuyo vencimiento (fecha_limite) está dentro de la ventana especificada y aún no fueron alertadas hoy.';

    public function handle(MailDispatcher $dispatcher): int
    {
        $this->checkScrum($dispatcher, (int) $this->option('scrum-hours'));

        return self::SUCCESS;
    }

    private function checkScrum(MailDispatcher $dispatcher, int $hours): void
    {
        $now      = now();
        $deadline = $now->copy()->addHours($hours);

        // Deadline efectivo: si la tarea fue reprogramada, se usa fecha_reprogramacion;
        // si no, fecha_limite. SLA chequea el deadline vigente, no el original.
        $tareas = Proyecto::query()
            ->with(['assignee', 'reporter', 'creator', 'aprobadoPor'])
            ->whereNotIn('status', ['Finalizado', 'Cancelado'])
            ->whereBetween(
                DB::raw('COALESCE(fecha_reprogramacion, fecha_limite)'),
                [$now->toDateString(), $deadline->toDateString()]
            )
            ->get();

        foreach ($tareas as $tarea) {
            // Dedupe diario: si ya se envió hoy una alerta SLA para esta tarea, saltar.
            if (GpMailLog::alreadySentToday('scrum.sla.approaching', Proyecto::class, $tarea->id)) {
                continue;
            }

            $recipients = collect([
                $tarea->assignee?->email,
                $tarea->reporter?->email,
                $tarea->creator?->email,
                $tarea->aprobadoPor?->email,
            ])->filter()->unique()->values()->all();

            if ($recipients === []) {
                continue;
            }

            $dispatcher->sendMailable(
                new \Modules\GestionProyectos\Mail\ScrumDeadlineApproachingMail($tarea, $hours),
                $recipients,
                meta: [
                    'trigger_type' => 'scrum.sla.approaching',
                    'model_type'   => Proyecto::class,
                    'model_id'     => $tarea->id,
                ],
            );

            $this->line("SCRUM SLA: notificado {$tarea->key} a " . implode(', ', $recipients));
        }
    }
}
