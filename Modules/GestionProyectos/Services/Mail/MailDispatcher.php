<?php

namespace Modules\GestionProyectos\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Modules\GestionProyectos\Models\GpMailLog;
use Modules\GestionProyectos\Models\GpMailProvider;
use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\AllProvidersFailedException;
use Modules\GestionProyectos\Services\Mail\Exceptions\MailProviderException;
use Modules\GestionProyectos\Services\Mail\Exceptions\NoProvidersAvailableException;
use Throwable;

/**
 * Orquestador central de envío de correos con failover + cuotas + logging.
 *
 * Reglas:
 *  - Recorre providers en orden de prioridad (resolver).
 *  - Cada provider tiene hasta 3 intentos internos con backoff 1s/2s/4s
 *    para errores retriables (timeout, 429 rate_limit, 5xx).
 *  - Si un provider devuelve quota_exceeded, se marca y se salta el día.
 *  - Si todos los providers fallan, lanza AllProvidersFailedException
 *    y deja el log en estado 'failed_all'.
 */
class MailDispatcher
{
    private const MAX_ATTEMPTS_PER_PROVIDER = 3;
    /** Backoff por default entre reintentos en milisegundos. Total peor caso por provider: 3.5s. */
    private const BACKOFF_MS = [500, 1000, 2000];

    /**
     * Tope de espera (segundos) cuando el provider manda un `Retry-After`.
     * Si el provider pide esperar más, conviene fallar este provider y saltar al
     * siguiente, en vez de bloquear el worker. La cola encima reintenta el job.
     */
    private const MAX_RETRY_AFTER_SECONDS = 30;

    /**
     * Cuando true (default), el `sendNow` aplica backoff entre reintentos.
     * Se puede desactivar en contextos sensibles a latencia (ej. llamada
     * síncrona desde un controller) para que el failover sea inmediato.
     */
    private bool $useBackoff = true;

    public function __construct(private readonly MailProviderResolver $resolver) {}

    /** Habilita/deshabilita el backoff entre reintentos. Útil para llamadas síncronas. */
    public function withBackoff(bool $enabled): self
    {
        $clone = clone $this;
        $clone->useBackoff = $enabled;
        return $clone;
    }

    private function maxRetryAfter(): int
    {
        return self::MAX_RETRY_AFTER_SECONDS;
    }

    /**
     * Envía un mensaje. NO bloqueante: por defecto encola un Job.
     * Para forzar envío síncrono pasar $queue=false.
     */
    public function send(MailMessageData $message, bool $queue = true): ?GpMailLog
    {
        if ($queue) {
            \Modules\GestionProyectos\Jobs\SendMailJob::dispatch($message->toArray());
            return null;
        }

        return $this->sendNow($message);
    }

    /**
     * Acepta un Mailable nativo de Laravel, lo renderiza a HTML y delega.
     * Helper para usar Blade + Mailable sin perder el orchestrator.
     *
     * @param string[] $recipients
     */
    public function sendMailable(Mailable $mailable, array $recipients, array $meta = [], bool $queue = true): ?GpMailLog
    {
        $html    = $mailable->render();
        $subject = $this->extractMailableSubject($mailable);

        $message = MailMessageData::make(
            to: $recipients,
            subject: $subject,
            html: $html,
            text: $this->stripHtml($html),
            meta: $meta,
        );

        return $this->send($message, $queue);
    }

    /**
     * Envío síncrono. Usado por el Job dentro de la cola.
     */
    public function sendNow(MailMessageData $message): GpMailLog
    {
        $log = $this->openLog($message);

        $providers = $this->resolver->availableProviders();
        if ($providers === []) {
            $log->update([
                'status'        => GpMailLog::STATUS_FAILED_ALL,
                'error_message' => 'No hay providers de correo disponibles.',
            ]);
            Log::critical('[MailDispatcher] No hay providers disponibles', [
                'recipient' => $log->recipient,
                'subject'   => $log->subject,
                'meta'      => $message->meta,
            ]);
            throw new NoProvidersAvailableException();
        }

        $providerErrors = [];

        foreach ($providers as $entry) {
            /** @var GpMailProvider $model */
            $model = $entry['model'];
            $provider = $entry['instance'];

            try {
                $result = $this->sendWithRetries($provider, $message, $log);
            } catch (MailProviderException $e) {
                $providerErrors[$provider->getName()] = $e->getMessage();

                if ($e->isQuotaExceeded()) {
                    $model->markFailure($e->getMessage(), GpMailProvider::STATUS_QUOTA_EXCEEDED);
                } elseif ($e->isAuthFailure()) {
                    $model->markFailure($e->getMessage(), GpMailProvider::STATUS_UNAVAILABLE);
                } else {
                    $model->markFailure($e->getMessage(), GpMailProvider::STATUS_DEGRADED);
                }
                continue;
            } catch (Throwable $e) {
                $providerErrors[$provider->getName()] = $e->getMessage();
                $model->markFailure($e->getMessage(), GpMailProvider::STATUS_DEGRADED);
                continue;
            }

            $model->markRecovered();
            $model->incrementUsed(count($message->to));

            $log->update([
                'provider'  => $provider->getName(),
                'status'    => GpMailLog::STATUS_SENT,
                'response'  => is_string($result['raw'] ?? null) ? $result['raw'] : json_encode($result, JSON_UNESCAPED_UNICODE),
                'sent_at'   => now(),
                'attempts'  => $log->attempts,
            ]);

            return $log;
        }

        $errorJson = json_encode($providerErrors, JSON_UNESCAPED_UNICODE);
        $log->update([
            'status'        => GpMailLog::STATUS_FAILED_ALL,
            'error_message' => $errorJson,
        ]);

        Log::critical('[MailDispatcher] Todos los providers fallaron', [
            'recipient' => $log->recipient,
            'subject'   => $log->subject,
            'errors'    => $providerErrors,
            'meta'      => $message->meta,
        ]);

        throw new AllProvidersFailedException($providerErrors);
    }

    /**
     * @return array{message_id: ?string, raw: ?string, status: int}
     */
    private function sendWithRetries(
        \Modules\GestionProyectos\Services\Mail\Contracts\MailProviderInterface $provider,
        MailMessageData $message,
        GpMailLog $log,
    ): array {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS_PER_PROVIDER; $attempt++) {
            $log->increment('attempts');

            try {
                return $provider->send($message);
            } catch (MailProviderException $e) {
                $lastException = $e;

                if (!$e->isRetriable() || $attempt === self::MAX_ATTEMPTS_PER_PROVIDER) {
                    throw $e;
                }

                // Si el provider mandó Retry-After mayor a nuestro tope, no esperamos:
                // fallamos este provider y dejamos que el failover/cola lo intenten más tarde.
                if ($e->retryAfterSeconds !== null && $e->retryAfterSeconds > self::MAX_RETRY_AFTER_SECONDS) {
                    Log::warning("[MailDispatcher] {$provider->getName()} pidió Retry-After={$e->retryAfterSeconds}s (> {$this->maxRetryAfter()}s), saltando al siguiente provider", [
                        'attempt' => $attempt,
                        'reason'  => $e->reason,
                    ]);
                    throw $e;
                }

                // Default backoff vs Retry-After del provider (cuando aplica).
                $defaultMs = self::BACKOFF_MS[$attempt - 1] ?? 2000;
                $backoffMs = $e->retryAfterSeconds !== null
                    ? max($defaultMs, $e->retryAfterSeconds * 1000)
                    : $defaultMs;

                Log::warning("[MailDispatcher] {$provider->getName()} fallo retriable, reintento en {$backoffMs}ms", [
                    'attempt'     => $attempt,
                    'reason'      => $e->reason,
                    'retry_after' => $e->retryAfterSeconds,
                ]);
                if ($this->useBackoff) {
                    usleep($backoffMs * 1000);
                }
            }
        }

        throw $lastException;
    }

    private function openLog(MailMessageData $message): GpMailLog
    {
        return GpMailLog::create([
            'recipient'    => $this->primaryRecipient($message),
            'subject'      => mb_substr($message->subject, 0, 255),
            'payload'      => [
                'to'   => $message->to,
                'meta' => $message->meta,
            ],
            'status'       => GpMailLog::STATUS_PENDING,
            'trigger_type' => $message->triggerType(),
            'model_type'   => $message->modelType(),
            'model_id'     => $message->modelId(),
            'attempts'     => 0,
        ]);
    }

    private function primaryRecipient(MailMessageData $message): string
    {
        if (count($message->to) === 1) {
            return $message->to[0];
        }
        return implode(', ', array_slice($message->to, 0, 5));
    }

    private function extractMailableSubject(Mailable $mailable): string
    {
        try {
            $reflection = new \ReflectionClass($mailable);
            if ($reflection->hasMethod('envelope')) {
                $envelope = $mailable->envelope();
                if (isset($envelope->subject) && is_string($envelope->subject) && $envelope->subject !== '') {
                    return $envelope->subject;
                }
            }
            if ($reflection->hasProperty('subject')) {
                $prop = $reflection->getProperty('subject');
                $prop->setAccessible(true);
                $value = $prop->getValue($mailable);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        } catch (Throwable) {
            // continúa abajo
        }
        return 'Notificación';
    }

    private function stripHtml(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        $text = preg_replace('/<\/(p|div|h\d|li)>/i', "\n", $text);
        return trim(strip_tags($text));
    }
}
