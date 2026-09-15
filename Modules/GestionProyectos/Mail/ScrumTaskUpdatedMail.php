<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumTaskUpdatedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $actorName;

    /** @var array<int, array{label:string, from:?string, to:?string}> */
    public array $changes;

    public function __construct(Proyecto $tarea, array $changes, string $actorName)
    {
        $this->key         = $tarea->key;
        $this->summaryText = (string) ($tarea->summary ?? '');
        $this->actorName   = $actorName;
        $this->changes     = $changes;

        $this->subjectLine = "[{$this->key}] Cambios en la tarea — {$this->summaryText}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.task-updated');
    }
}
