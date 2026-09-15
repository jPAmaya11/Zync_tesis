<?php

namespace Modules\GestionProyectos\Services\Mail\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\GestionProyectos\Models\GpMailProvider;
use Modules\GestionProyectos\Services\Mail\Contracts\MailProviderInterface;
use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\MailProviderException;
use Throwable;

abstract class AbstractHttpMailProvider implements MailProviderInterface
{
    public function __construct(
        protected readonly GpMailProvider $providerModel,
    ) {}

    abstract public function getName(): string;

    abstract protected function buildPayload(MailMessageData $message, string $fromAddress, string $fromName): array;

    abstract protected function buildHeaders(): array;

    abstract protected function extractMessageId(Response $response): ?string;

    public function validate(): bool
    {
        return $this->getApiKey() !== null && $this->getEndpoint() !== '';
    }

    /**
     * Default: el provider NO soporta API de usage. Override en providers concretos
     * (ej. SendGrid) para habilitar sync real contra el dashboard del provider.
     */
    public function supportsUsageApi(): bool
    {
        return false;
    }

    /**
     * Default: null. Override en providers que sí soportan usage API.
     */
    public function fetchUsageStats(): ?array
    {
        return null;
    }

    public function send(MailMessageData $message): array
    {
        if (!$this->validate()) {
            throw new MailProviderException(
                "Provider {$this->getName()} no tiene credenciales o endpoint configurados.",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_AUTH,
            );
        }

        $fromAddress = (string) config('mail.from.address', 'no-reply@example.com');
        $fromName    = (string) config('mail.from.name', config('app.name', 'Zync'));

        $payload  = $this->buildPayload($message, $fromAddress, $fromName);
        $headers  = $this->buildHeaders();
        $endpoint = $this->getEndpoint();
        $timeout  = (int) ($this->providerModel->config['timeout'] ?? 15);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new MailProviderException(
                "Timeout o conexión fallida con {$this->getName()}: {$e->getMessage()}",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_TIMEOUT,
                previous: $e,
            );
        } catch (Throwable $e) {
            throw new MailProviderException(
                "Error inesperado al invocar {$this->getName()}: {$e->getMessage()}",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_UNKNOWN,
                previous: $e,
            );
        }

        if ($response->successful()) {
            return [
                'message_id' => $this->extractMessageId($response),
                'raw'        => $this->safeResponseBody($response),
                'status'     => $response->status(),
            ];
        }

        throw $this->mapHttpError($response);
    }

    protected function getApiKey(): ?string
    {
        $key = $this->providerModel->config['api_key'] ?? null;
        if (is_string($key) && trim($key) !== '') {
            return $key;
        }

        $envKey = match ($this->getName()) {
            'resend'   => 'RESEND_API_KEY',
            'sendgrid' => 'SENDGRID_API_KEY',
            default    => null,
        };

        if ($envKey === null) {
            return null;
        }

        $value = env($envKey);
        return (is_string($value) && trim($value) !== '') ? $value : null;
    }

    protected function getEndpoint(): string
    {
        return (string) ($this->providerModel->config['endpoint'] ?? '');
    }

    protected function mapHttpError(Response $response): MailProviderException
    {
        $status = $response->status();
        $body   = $this->safeResponseBody($response);
        $reason = match (true) {
            $status === 401 || $status === 403 => MailProviderException::REASON_AUTH,
            $status === 422                    => $this->detect422Reason($response, $body),
            $status === 429                    => $this->detectRateLimitReason($response, $body),
            $status >= 500                     => MailProviderException::REASON_SERVER,
            default                            => MailProviderException::REASON_UNKNOWN,
        };

        // Leer header HTTP Retry-After si está presente (típicamente en 429 / 503).
        $retryAfterSeconds = $this->parseRetryAfter($response->header('Retry-After'));

        return new MailProviderException(
            "{$this->getName()} respondió HTTP {$status}: " . mb_substr((string) $body, 0, 500),
            providerName: $this->getName(),
            reason: $reason,
            httpStatus: $status,
            responseBody: $body,
            retryAfterSeconds: $retryAfterSeconds,
        );
    }

    /**
     * Parsea el header HTTP `Retry-After`. Acepta los dos formatos válidos:
     *   - Integer en segundos: "120"
     *   - HTTP-date RFC 7231: "Wed, 21 Oct 2026 07:28:00 GMT"
     *
     * Retorna `null` si está ausente, vacío o no se puede interpretar.
     * Negativos o cero se devuelven como 0 (sin espera).
     */
    protected function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        $seconds = $timestamp - time();
        return max(0, $seconds);
    }

    /**
     * Detecta si un 429 es cuota agotada (no retriable, marca provider) o rate limit transitorio (retriable).
     *
     * Base default: inspecciona headers estándar `X-RateLimit-Remaining` y `Retry-After`,
     * y como fallback hace string matching. Cada provider puede sobreescribir con su formato.
     */
    protected function detectRateLimitReason(Response $response, ?string $body): string
    {
        // Header estándar: si está en 0 explícitamente, agotada.
        $remaining = $response->header('X-RateLimit-Remaining');
        if ($remaining !== null && $remaining !== '' && (int) $remaining === 0) {
            return MailProviderException::REASON_QUOTA_EXCEEDED;
        }

        $haystack = strtolower((string) $body);
        if (str_contains($haystack, 'quota') || str_contains($haystack, 'daily') || str_contains($haystack, 'monthly')) {
            return MailProviderException::REASON_QUOTA_EXCEEDED;
        }

        return MailProviderException::REASON_RATE_LIMIT;
    }

    /**
     * Algunos providers responden 422 con cuerpos que en realidad significan quota.
     * Override por provider si aplica.
     */
    protected function detect422Reason(Response $response, ?string $body): string
    {
        return MailProviderException::REASON_INVALID;
    }

    protected function safeResponseBody(Response $response): ?string
    {
        try {
            return $response->body() ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
