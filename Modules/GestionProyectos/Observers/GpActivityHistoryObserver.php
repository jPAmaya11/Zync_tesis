<?php

namespace Modules\GestionProyectos\Observers;

use Modules\GestionProyectos\Mail\ScrumCommentMail;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;
use Modules\GestionProyectos\Support\CriticalTransitionContext;
use Modules\GestionProyectos\Support\MailRecipients;

/**
 * Cuando se crea una entrada en gp_activity_history (comentario / adjunto),
 * notifica a los roles directos de la tarea.
 *
 * Cubre triggers:
 *   - Comentarios (sin adjunto)
 *   - Archivos adjuntos (con o sin comentario)
 *
 * Excepción: si la entrada se crea como evidencia para una transición crítica
 * (marcado por CriticalTransitionContext), se suprime el correo de "nuevo
 * comentario". El correo único de "cambio de estado" lo emite
 * ProyectoNotificationObserver e incluye comentario + adjunto.
 */
class GpActivityHistoryObserver
{
    public function created(GpActivityHistory $entry): void
    {
        // Evita doble email: la transición crítica se notifica en un único
        // correo con la evidencia embebida.
        if (CriticalTransitionContext::inside()) {
            return;
        }

        $tarea = Proyecto::with(['assignee', 'reporter', 'creator', 'aprobadoPor'])
            ->where('key', $entry->tarea_key)
            ->first();

        if (!$tarea) {
            return;
        }

        $recipients = MailRecipients::forScrumTask($tarea, excludeUserId: $entry->user_id);
        if ($recipients === []) {
            return;
        }

        $hasAttachment = $entry->attachment_path !== null && $entry->attachment_path !== '';
        $triggerType   = $hasAttachment ? 'scrum.attachment.added' : 'scrum.comment.added';

        app(MailDispatcher::class)->sendMailable(
            new ScrumCommentMail($tarea, $entry, $hasAttachment),
            $recipients,
            meta: [
                'trigger_type' => $triggerType,
                'model_type'   => Proyecto::class,
                'model_id'     => $tarea->id,
            ],
        );
    }
}
