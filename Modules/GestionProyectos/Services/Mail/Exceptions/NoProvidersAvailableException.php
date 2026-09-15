<?php

namespace Modules\GestionProyectos\Services\Mail\Exceptions;

use RuntimeException;

class NoProvidersAvailableException extends RuntimeException
{
    public function __construct(string $message = 'No hay providers de correo disponibles (todos deshabilitados, sin cuota o caídos).')
    {
        parent::__construct($message);
    }
}
