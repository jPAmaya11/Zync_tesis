<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumTaskAssignedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $statusText;
    public string $priorityText;
    public string $assigneeName;
    public string $actorName;
    public ?string $dueDate;
    public bool $isForAssignee;

    public function __construct(Proyecto $tarea, string $actorName, ?string $recipientEmail = null)
    {
        $this->key           = $tarea->key;
        $this->summaryText   = (string) ($tarea->summary ?? '');
        $this->statusText    = (string) ($tarea->status ?? 'Pendiente');
        $this->priorityText  = (string) ($tarea->priority ?? 'Media');
        $this->assigneeName  = $tarea->assignee?->name ?? 'Sin asignar';
        $this->actorName     = $actorName;
        $this->dueDate       = optional($tarea->fecha_limite)->format('d/m/Y');
        $this->isForAssignee = $recipientEmail !== null && $tarea->assignee?->email === $recipientEmail;

        $this->subjectLine = $this->isForAssignee
            ? "[{$this->key}] Te asignaron una tarea — {$this->summaryText}"
            : "[{$this->key}] Tarea reasignada a {$this->assigneeName}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.task-assigned');
    }
}
