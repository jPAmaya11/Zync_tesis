<?php

namespace Modules\GestionProyectos\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\GestionProyectos\Mail\ScrumStatusChangedMail;
use Modules\GestionProyectos\Mail\ScrumTaskAssignedMail;
use Modules\GestionProyectos\Mail\ScrumTaskCreatedMail;
use Modules\GestionProyectos\Mail\ScrumTaskUnassignedMail;
use Modules\GestionProyectos\Mail\ScrumTaskUpdatedMail;
use Modules\GestionProyectos\Mail\ScrumTeamAssignedMail;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;
use Modules\GestionProyectos\Support\BulkContext;
use Modules\GestionProyectos\Support\MailRecipients;
use Modules\User\Models\User;

/**
 * Observer dedicado a notificaciones por correo (separado del observer de auditoría).
 *
 * Triggers cubiertos:
 *   1. Creación de tarea  → ScrumTaskCreatedMail
 *   2. Asignación / reasignación → ScrumTaskAssignedMail
 *   3. Modificaciones de contenido humanas → ScrumTaskUpdatedMail
 *   5+6. Cambio de estado / transición de flujo → ScrumStatusChangedMail
 *
 * Durante una operación masiva (BulkContext::inside()) los correos
 * individuales no se envían; se registra en BulkContext y se emite un
 * único digest por destinatario al cerrar la operación.
 */
class ProyectoNotificationObserver
{
    /**
     * Campos "humanos" que disparan correo de "modificación de contenido".
     * Se excluyen explícitamente los derivados (fecha_limite, fecha_aprobacion,
     * fecha_entrega, aprobado_por_id, solicitado_por) porque se llenan
     * automáticamente por boot() de Proyecto o por la lógica de transiciones.
     */
    private const HUMAN_FIELDS = [
        'summary'        => 'Resumen',
        'description'    => 'Descripción',
        'priority'       => 'Prioridad',
        'fecha_limite'   => 'Fecha límite',
        'start_date'     => 'Fecha inicio',
        'dias_estimados' => 'Días estimados',
        'labels'         => 'Etiquetas',
        'software'       => 'Software',
        'entorno'        => 'Entorno',
        'impacto'        => 'Impacto',
        'reporter_id'    => 'Reportador',
    ];

    public function created(Proyecto $tarea): void
    {
        $actorId   = Auth::id();
        $actorName = Auth::user()?->name ?? 'Sistema';

        // Si nace asignada a un equipo, notificar a sus miembros.
        if ($tarea->team_id) {
            $this->notifyTeamAssigned($tarea, (int) $tarea->team_id, $actorName);
        }

        $recipients = MailRecipients::forScrumTask(
            $tarea->loadMissing(['assignee', 'reporter', 'creator', 'aprobadoPor']),
            excludeUserId: $actorId,
        );

        if ($recipients === []) {
            return;
        }

        // Si hay assignee inicial, mandamos UN correo de "creación" (la plantilla
        // se adapta para decir "te asignaron"). Esa fue la decisión: 1 solo correo.
        foreach ($recipients as $email) {
            $mail = new ScrumTaskCreatedMail($tarea, $email);
            app(MailDispatcher::class)->sendMailable(
                $mail,
                [$email],
                meta: [
                    'trigger_type' => 'scrum.task.created',
                    'model_type'   => Proyecto::class,
                    'model_id'     => $tarea->id,
                ],
            );
        }
    }

    public function updated(Proyecto $tarea): void
    {
        $dirty = $tarea->getDirty();
        if ($dirty === []) {
            return;
        }

        $actorId   = Auth::id();
        $actorName = Auth::user()?->name ?? 'Sistema';

        // --- Cambio de assignee (reasignación) ------------------------------
        if (array_key_exists('assignee_id', $dirty)) {
            $tarea->loadMissing(['assignee', 'reporter', 'creator']);
            // Reasignación: nuevo + reporter/creator (NO al anterior).
            $recipients = MailRecipients::forScrumReassignment($tarea, excludeUserId: $actorId);

            if ($recipients !== [] && !BulkContext::inside()) {
                foreach ($recipients as $email) {
                    $mail = new ScrumTaskAssignedMail($tarea, $actorName, $email);
                    app(MailDispatcher::class)->sendMailable(
                        $mail,
                        [$email],
                        meta: [
                            'trigger_type' => 'scrum.task.assigned',
                            'model_type'   => Proyecto::class,
                            'model_id'     => $tarea->id,
                        ],
                    );
                }
            }

            // Notificar al ex-asignado (si existía) que ya no es responsable.
            // Se omite si el actor es el mismo ex-asignado (se auto-desasignó).
            $previousAssigneeId = $tarea->getOriginal('assignee_id');
            if ($previousAssigneeId
                && $previousAssigneeId !== $actorId
                && !BulkContext::inside()
            ) {
                $previousAssignee = User::find($previousAssigneeId);
                if ($previousAssignee?->email) {
                    $mail = new ScrumTaskUnassignedMail($tarea, $previousAssignee, $actorName);
                    app(MailDispatcher::class)->sendMailable(
                        $mail,
                        [$previousAssignee->email],
                        meta: [
                            'trigger_type' => 'scrum.task.unassigned',
                            'model_type'   => Proyecto::class,
                            'model_id'     => $tarea->id,
                        ],
                    );
                }
            }
        }

        // --- Cambio de equipo asignado --------------------------------------
        if (array_key_exists('team_id', $dirty) && !empty($dirty['team_id']) && !BulkContext::inside()) {
            $this->notifyTeamAssigned($tarea, (int) $dirty['team_id'], $actorName);
        }

        // --- Cambio de estado ----------------------------------------------
        if (array_key_exists('status', $dirty)) {
            $tarea->loadMissing(['assignee', 'reporter', 'creator', 'aprobadoPor']);
            $recipients = MailRecipients::forScrumTask($tarea, excludeUserId: $actorId);

            if ($recipients !== [] && !BulkContext::inside()) {
                $fromStatus = (string) ($tarea->getOriginal('status') ?? '—');
                $toStatus   = (string) $dirty['status'];

                // Si la transición es crítica, embebemos la evidencia más reciente
                // (comentario + adjunto opcional) en el correo. Así el aprobador
                // ve el "porqué" sin tener que entrar al ERP.
                $evidence = null;
                $criticals = config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']);
                if (in_array($toStatus, $criticals, true)) {
                    $evidence = GpActivityHistory::where('tarea_key', $tarea->key)
                        ->whereNotNull('comment')
                        ->where('comment', '!=', '')
                        ->where('created_at', '>=', now()->subHours(36))
                        ->orderByDesc('created_at')
                        ->first();
                }

                $mail = new ScrumStatusChangedMail($tarea, $fromStatus, $toStatus, $actorName, $evidence);
                app(MailDispatcher::class)->sendMailable(
                    $mail,
                    $recipients,
                    meta: [
                        'trigger_type' => 'scrum.status.changed',
                        'model_type'   => Proyecto::class,
                        'model_id'     => $tarea->id,
                    ],
                );
            }
        }

        // --- Modificaciones humanas (sin status/assignee) ------------------
        $humanChanges = $this->extractHumanChanges($tarea, $dirty);

        if ($humanChanges !== []) {
            $tarea->loadMissing(['assignee', 'reporter', 'creator', 'aprobadoPor']);
            $recipientEmails = MailRecipients::forScrumTask($tarea, excludeUserId: $actorId);
            $recipientUserIds = collect([
                $tarea->assignee_id, $tarea->reporter_id, $tarea->creator_id, $tarea->aprobado_por_id,
            ])->filter()->reject(fn ($id) => (int) $id === (int) $actorId)->unique()->values()->all();

            if ($recipientEmails === []) {
                return;
            }

            if (BulkContext::inside()) {
                BulkContext::record(
                    key: $tarea->key,
                    summary: (string) $tarea->summary,
                    changes: $humanChanges,
                    recipientUserIds: $recipientUserIds,
                );
                return;
            }

            $mail = new ScrumTaskUpdatedMail($tarea, $humanChanges, $actorName);
            app(MailDispatcher::class)->sendMailable(
                $mail,
                $recipientEmails,
                meta: [
                    'trigger_type' => 'scrum.task.updated',
                    'model_type'   => Proyecto::class,
                    'model_id'     => $tarea->id,
                ],
            );
        }
    }

    /**
     * Notifica a todos los miembros del equipo que su equipo fue asignado a la tarea.
     * Se omiten miembros sin email. No se excluye al actor (decisión: notificar a todos).
     */
    private function notifyTeamAssigned(Proyecto $tarea, int $teamId, string $actorName): void
    {
        $team = GpTeam::with('members')->find($teamId);
        if (!$team) {
            return;
        }

        foreach ($team->members as $member) {
            if (!$member->email) {
                continue;
            }
            $mail = new ScrumTeamAssignedMail($tarea, $team, $actorName);
            app(MailDispatcher::class)->sendMailable(
                $mail,
                [$member->email],
                meta: [
                    'trigger_type' => 'scrum.team.assigned',
                    'model_type'   => Proyecto::class,
                    'model_id'     => $tarea->id,
                ],
            );
        }
    }

    /** Cache de nombres de usuario por id durante el request — evita N+1 en bulkUpdate. */
    private static array $userNameCache = [];

    private static function userName($id): ?string
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        return self::$userNameCache[$id] ??= User::find($id)?->name;
    }

    /**
     * Construye la lista [{label, from, to}] sólo con campos humanos relevantes.
     *
     * @return array<int, array{label:string, from:?string, to:?string}>
     */
    private function extractHumanChanges(Proyecto $tarea, array $dirty): array
    {
        $out = [];

        foreach (self::HUMAN_FIELDS as $field => $label) {
            if (!array_key_exists($field, $dirty)) {
                continue;
            }

            $from = $tarea->getOriginal($field);
            $to   = $dirty[$field];

            // Resolver IDs a nombres legibles. team_id NO está en HUMAN_FIELDS
            // (tiene su propio flujo de notificación), por eso no se contempla aquí.
            if ($field === 'reporter_id') {
                $from = self::userName($from);
                $to   = self::userName($to);
            } elseif (in_array($field, ['labels', 'impacto'], true)) {
                $from = is_array($from) ? implode(', ', $from) : (is_string($from) && $from !== '' ? implode(', ', (array) json_decode($from, true)) : null);
                $to   = is_array($to) ? implode(', ', $to) : (is_string($to) && $to !== '' ? implode(', ', (array) json_decode($to, true)) : null);
            } else {
                $from = $from !== null ? (string) $from : null;
                $to   = $to !== null ? (string) $to : null;
            }

            if ($from === $to) {
                continue;
            }

            $out[] = ['label' => $label, 'from' => $from, 'to' => $to];
        }

        return $out;
    }
}
