<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumTaskCreatedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $statusText;
    public string $priorityText;
    public string $projectKey;
    public string $createdBy;
    public ?string $assigneeName;
    public ?string $startDate;
    public ?string $dueDate;
    public bool $forAssignee;

    public function __construct(Proyecto $tarea, ?string $recipientEmail = null)
    {
        $this->key          = $tarea->key;
        $this->summaryText  = (string) ($tarea->summary ?? '');
        $this->statusText   = (string) ($tarea->status ?? 'Pendiente');
        $this->priorityText = (string) ($tarea->priority ?? 'Media');
        $this->projectKey   = (string) ($tarea->project ?? '');
        $this->createdBy    = $tarea->creator?->name ?? $tarea->reporter?->name ?? 'Sistema';
        $this->assigneeName = $tarea->assignee?->name;
        $this->startDate    = optional($tarea->start_date)->format('d/m/Y');
        $this->dueDate      = optional($tarea->fecha_limite)->format('d/m/Y');
        $this->forAssignee  = $recipientEmail !== null && $tarea->assignee?->email === $recipientEmail;

        $this->subjectLine  = "[{$this->key}] Nueva tarea creada — {$this->summaryText}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.task-created');
    }
}
