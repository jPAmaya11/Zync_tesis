<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Correo digest para bulkUpdate: una sola entrega por destinatario que resume
 * todos los cambios masivos realizados en una misma acción.
 */
class ScrumBulkDigestMail extends BaseScrumMail
{
    public string $actorName;
    /** @var array<int, array{key:string, summary:string, changes:array}> */
    public array $items;

    public function __construct(string $actorName, array $items)
    {
        $this->actorName = $actorName;
        $this->items     = $items;
        $count           = count($items);
        $this->subjectLine = "Resumen de cambios masivos en {$count} " . ($count === 1 ? 'tarea' : 'tareas');
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.bulk-digest');
    }
}
