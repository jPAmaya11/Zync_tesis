<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Notifica a los miembros de un equipo que su equipo fue asignado a una tarea SCRUM.
 * Se dispara desde ProyectoNotificationObserver cuando team_id se setea (crear o editar).
 */
class ScrumTeamAssignedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $statusText;
    public string $priorityText;
    public string $teamName;
    public string $actorName;
    public ?string $dueDate;

    public function __construct(Proyecto $tarea, GpTeam $team, string $actorName)
    {
        $this->key          = $tarea->key;
        $this->summaryText  = (string) ($tarea->summary ?? '');
        $this->statusText   = (string) ($tarea->status ?? 'Pendiente');
        $this->priorityText = (string) ($tarea->priority ?? 'Media');
        $this->teamName     = $team->name;
        $this->actorName    = $actorName;
        $this->dueDate      = optional($tarea->fecha_reprogramacion ?: $tarea->fecha_limite)->format('d/m/Y');

        $this->subjectLine = "[{$this->key}] Tu equipo {$this->teamName} fue asignado a esta tarea";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.task-team-assigned');
    }
}
