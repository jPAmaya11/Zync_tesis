<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumDeadlineApproachingMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $statusText;
    public string $priorityText;
    public ?string $dueDate;
    public int $windowHours;

    public function __construct(Proyecto $tarea, int $windowHours)
    {
        $this->key          = $tarea->key;
        $this->summaryText  = (string) ($tarea->summary ?? '');
        $this->statusText   = (string) ($tarea->status ?? 'Pendiente');
        $this->priorityText = (string) ($tarea->priority ?? 'Media');
        $this->dueDate      = optional($tarea->fecha_limite)->format('d/m/Y');
        $this->windowHours  = $windowHours;

        $this->subjectLine = "[{$this->key}] Vencimiento próximo — {$this->summaryText}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.deadline-approaching');
    }
}
