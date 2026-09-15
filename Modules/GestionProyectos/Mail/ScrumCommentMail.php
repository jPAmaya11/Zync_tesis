<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\Proyecto;

class ScrumCommentMail extends BaseScrumMail
{
    public string $key;
    public string $summaryText;
    public string $actorName;
    public ?string $commentText;
    public bool $hasAttachment;
    public ?string $attachmentName;

    public function __construct(Proyecto $tarea, GpActivityHistory $entry, bool $hasAttachment)
    {
        $this->key            = $tarea->key;
        $this->summaryText    = (string) ($tarea->summary ?? '');
        $this->actorName      = $entry->user?->name ?? 'Usuario';
        $this->commentText    = $entry->comment;
        $this->hasAttachment  = $hasAttachment;
        $this->attachmentName = $entry->attachment_name;

        $this->subjectLine = $hasAttachment
            ? "[{$this->key}] Nuevo adjunto — {$this->summaryText}"
            : "[{$this->key}] Nuevo comentario — {$this->summaryText}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.comment');
    }
}
