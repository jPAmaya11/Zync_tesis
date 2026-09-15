<?php

namespace Modules\GestionProyectos\Services\Mail\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\MailProviderException;
use Throwable;

class SendGridProvider extends AbstractHttpMailProvider
{
    private const STATS_ENDPOINT = 'https://api.sendgrid.com/v3/stats';

    public function getName(): string
    {
        return 'sendgrid';
    }

    public function supportsUsageApi(): bool
    {
        return true;
    }

    /**
     * Llama a GET /v3/stats?aggregated_by=day&start_date=YYYY-MM-01&end_date=YYYY-MM-DD
     * y suma los `requests` (lo que SendGrid contabiliza contra la cuota) por día.
     *
     * @return array{used_today:int, used_this_month:int}
     * @throws MailProviderException
     */
    public function fetchUsageStats(): ?array
    {
        if (!$this->validate()) {
            throw new MailProviderException(
                "SendGrid sin credenciales para fetchUsageStats.",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_AUTH,
            );
        }

        // Rango: mes en curso en America/Lima → fechas YYYY-MM-DD (SendGrid acepta fecha simple)
        $now          = now('America/Lima');
        $startOfMonth = $now->copy()->startOfMonth()->format('Y-m-d');
        $today        = $now->copy()->format('Y-m-d');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Accept'        => 'application/json',
            ])
                ->timeout((int) ($this->providerModel->config['timeout'] ?? 15))
                ->get(self::STATS_ENDPOINT, [
                    'aggregated_by' => 'day',
                    'start_date'    => $startOfMonth,
                    'end_date'      => $today,
                ]);
        } catch (ConnectionException $e) {
            throw new MailProviderException(
                "Timeout consultando /v3/stats: {$e->getMessage()}",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_TIMEOUT,
                previous: $e,
            );
        } catch (Throwable $e) {
            throw new MailProviderException(
                "Error consultando /v3/stats: {$e->getMessage()}",
                providerName: $this->getName(),
                reason: MailProviderException::REASON_UNKNOWN,
                previous: $e,
            );
        }

        if (!$response->successful()) {
            throw $this->mapHttpError($response);
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['used_today' => 0, 'used_this_month' => 0];
        }

        $usedThisMonth = 0;
        $usedToday     = 0;

        foreach ($data as $dayBucket) {
            $date = $dayBucket['date'] ?? null;
            $stats = $dayBucket['stats'][0]['metrics'] ?? null;
            if (!is_array($stats)) {
                continue;
            }
            // `requests` = total de correos procesados ese día (es lo que cuenta para la cuota).
            $requests = (int) ($stats['requests'] ?? 0);
            $usedThisMonth += $requests;
            if ($date === $today) {
                $usedToday = $requests;
            }
        }

        return [
            'used_today'      => $usedToday,
            'used_this_month' => $usedThisMonth,
        ];
    }

    /**
     * SendGrid responde 429 con headers X-RateLimit-Remaining y X-RateLimit-Reset, y body:
     *   { "errors": [ { "message": "Maximum credits exceeded", "field": null, "help": null } ] }
     * o
     *   { "errors": [ { "message": "rate limit exceeded", ... } ] }
     *
     * Cuando es credit/quota exhaustion, usualmente el mensaje contiene "credits" o "limit".
     * Ref: https://docs.sendgrid.com/api-reference/how-to-use-the-sendgrid-v3-api/rate-limits
     */
    protected function detectRateLimitReason(Response $response, ?string $body): string
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        if ($remaining !== null && $remaining !== '' && (int) $remaining === 0) {
            // Si Retry-After es >= 1 hora, asumimos quota diaria, no rate transitorio.
            $retryAfter = (int) $response->header('Retry-After');
            if ($retryAfter >= 3600) {
                return MailProviderException::REASON_QUOTA_EXCEEDED;
            }
        }

        $json = $response->json();
        $errors = is_array($json) ? ($json['errors'] ?? []) : [];
        $combined = '';
        if (is_array($errors)) {
            foreach ($errors as $err) {
                if (is_array($err) && isset($err['message'])) {
                    $combined .= ' ' . strtolower((string) $err['message']);
                }
            }
        }

        if (str_contains($combined, 'credits') || str_contains($combined, 'maximum') || str_contains($combined, 'quota')) {
            return MailProviderException::REASON_QUOTA_EXCEEDED;
        }

        if (str_contains($combined, 'rate limit')) {
            return MailProviderException::REASON_RATE_LIMIT;
        }

        return parent::detectRateLimitReason($response, $body);
    }

    protected function buildPayload(MailMessageData $message, string $fromAddress, string $fromName): array
    {
        $content = [];
        if ($message->text !== null && $message->text !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $message->text];
        }
        $content[] = ['type' => 'text/html', 'value' => $message->html];

        return [
            'personalizations' => [[
                'to' => array_map(fn (string $email) => ['email' => $email], $message->to),
            ]],
            'from'    => array_filter([
                'email' => $fromAddress,
                'name'  => $fromName ?: null,
            ]),
            'subject' => $message->subject,
            'content' => $content,
        ];
    }

    protected function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type'  => 'application/json',
        ];
    }

    protected function extractMessageId(Response $response): ?string
    {
        return $response->header('X-Message-Id') ?: null;
    }
}
