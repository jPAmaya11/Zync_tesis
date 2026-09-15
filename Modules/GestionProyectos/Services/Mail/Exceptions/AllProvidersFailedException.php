<?php

namespace Modules\GestionProyectos\Services\Mail\Exceptions;

use RuntimeException;

class AllProvidersFailedException extends RuntimeException
{
    /** @param array<string,string> $providerErrors map provider => error message */
    public function __construct(
        public readonly array $providerErrors,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? 'Todos los providers de correo fallaron: ' . json_encode($providerErrors, JSON_UNESCAPED_UNICODE),
        );
    }
}
