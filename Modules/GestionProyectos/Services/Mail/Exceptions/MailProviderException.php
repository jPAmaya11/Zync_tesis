<?php

namespace Modules\GestionProyectos\Services\Mail\Exceptions;

use RuntimeException;
use Throwable;

class MailProviderException extends RuntimeException
{
    public const REASON_TIMEOUT = 'timeout';
    public const REASON_RATE_LIMIT = 'rate_limit';
    public const REASON_QUOTA_EXCEEDED = 'quota_exceeded';
    public const REASON_AUTH = 'auth';
    public const REASON_SERVER = 'server';
    public const REASON_INVALID = 'invalid';
    public const REASON_UNKNOWN = 'unknown';

    private const RETRIABLE = [
        self::REASON_TIMEOUT,
        self::REASON_RATE_LIMIT,
        self::REASON_SERVER,
    ];

    public function __construct(
        string $message,
        public readonly string $providerName,
        public readonly string $reason = self::REASON_UNKNOWN,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseBody = null,
        /**
         * Si el provider mandó el header HTTP `Retry-After` (en segundos), va acá.
         * El dispatcher lo respeta para esperar antes del siguiente intento.
         */
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetriable(): bool
    {
        return in_array($this->reason, self::RETRIABLE, true);
    }

    public function isQuotaExceeded(): bool
    {
        return $this->reason === self::REASON_QUOTA_EXCEEDED;
    }

    public function isAuthFailure(): bool
    {
        return $this->reason === self::REASON_AUTH;
    }
}
