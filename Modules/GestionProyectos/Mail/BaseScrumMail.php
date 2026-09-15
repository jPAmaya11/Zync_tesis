<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base común para todos los Mailables del módulo SCRUM.
 * No serializa modelos Eloquent: cada hija expone propiedades planas para la vista.
 */
abstract class BaseScrumMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine = 'Notificación';

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(subject: $this->subjectLine);
    }
}
