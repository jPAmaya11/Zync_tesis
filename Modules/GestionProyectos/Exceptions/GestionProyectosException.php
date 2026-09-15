<?php

namespace Modules\GestionProyectos\Exceptions;

use Exception;
use Throwable;

class GestionProyectosException extends Exception
{
    protected array $context = [];

    public function __construct(string $message, int $code = 0, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function isNotFound(): bool
    {
        return $this->getCode() === 404;
    }

    public function isValidationError(): bool
    {
        return $this->getCode() === 422;
    }
}
