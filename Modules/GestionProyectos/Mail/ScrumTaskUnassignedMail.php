<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;

/**
 * Notifica al ex-asignado de una tarea SCRUM que ya no es su responsabilidad.
 * Se dispara en ProyectoNotificationObserver cuando assignee_id cambia y había
 * un valor anterior; va únicamente al usuario que perdió la asignación.
 */
class ScrumTaskUnassignedMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $statusText;
    public string $priorityText;
    public string $previousAssigneeName;
    public string $newAssigneeName;
    public string $actorName;
    public ?string $dueDate;

    public function __construct(Proyecto $tarea, User $previousAssignee, string $actorName)
    {
        $this->key                  = $tarea->key;
        $this->summaryText          = (string) ($tarea->summary ?? '');
        $this->statusText           = (string) ($tarea->status ?? 'Pendiente');
        $this->priorityText         = (string) ($tarea->priority ?? 'Media');
        $this->previousAssigneeName = $previousAssignee->name;
        $this->newAssigneeName      = $tarea->assignee?->name ?? 'Sin asignar';
        $this->actorName            = $actorName;
        $this->dueDate              = optional($tarea->fecha_limite)->format('d/m/Y');

        $this->subjectLine = "[{$this->key}] Ya no estás asignado a esta tarea";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.task-unassigned');
    }
}
