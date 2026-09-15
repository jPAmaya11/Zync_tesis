<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumStatusChangedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $fromStatus;
    public string $toStatus;
    public string $actorName;
    public bool $isCritical;
    public ?string $evidenceComment = null;
    public ?string $evidenceAttachmentName = null;
    public ?int $evidenceId = null;

    public function __construct(Proyecto $tarea, string $fromStatus, string $toStatus, string $actorName, ?GpActivityHistory $evidence = null)
    {
        $this->key         = $tarea->key;
        $this->summaryText = (string) ($tarea->summary ?? '');
        $this->fromStatus  = $fromStatus;
        $this->toStatus    = $toStatus;
        $this->actorName   = $actorName;
        $this->isCritical  = in_array($toStatus, config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']), true);

        if ($evidence) {
            $this->evidenceComment        = $evidence->comment;
            $this->evidenceAttachmentName = $evidence->attachment_path ? $evidence->attachment_name : null;
            $this->evidenceId             = $evidence->attachment_path ? $evidence->id : null;
        }

        $this->subjectLine = $this->isCritical
            ? "[{$this->key}] {$toStatus} — {$this->summaryText}"
            : "[{$this->key}] Estado actualizado a {$toStatus}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.status-changed');
    }
}
