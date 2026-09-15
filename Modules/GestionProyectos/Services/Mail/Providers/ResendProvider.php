<?php

namespace Modules\GestionProyectos\Services\Mail\Providers;

use Illuminate\Http\Client\Response;
use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\MailProviderException;

class ResendProvider extends AbstractHttpMailProvider
{
    public function getName(): string
    {
        return 'resend';
    }

    /**
     * Resend devuelve errores con shape:
     *   { "statusCode": 429, "name": "rate_limit_exceeded", "message": "..." }
     *   { "statusCode": 429, "name": "daily_quota_exceeded", "message": "..." }
     *   { "statusCode": 401, "name": "missing_api_key", ... }
     *
     * Ref: https://resend.com/docs/api-reference/errors
     */
    protected function detectRateLimitReason(Response $response, ?string $body): string
    {
        $json = $response->json();
        $name = is_array($json) ? strtolower((string) ($json['name'] ?? '')) : '';

        $quotaCodes = ['daily_quota_exceeded', 'monthly_quota_exceeded', 'quota_exceeded'];
        if (in_array($name, $quotaCodes, true)) {
            return MailProviderException::REASON_QUOTA_EXCEEDED;
        }

        if ($name === 'rate_limit_exceeded') {
            return MailProviderException::REASON_RATE_LIMIT;
        }

        return parent::detectRateLimitReason($response, $body);
    }

    protected function buildPayload(MailMessageData $message, string $fromAddress, string $fromName): array
    {
        $payload = [
            'from'    => $fromName ? "{$fromName} <{$fromAddress}>" : $fromAddress,
            'to'      => $message->to,
            'subject' => $message->subject,
            'html'    => $message->html,
        ];

        if ($message->text !== null && $message->text !== '') {
            $payload['text'] = $message->text;
        }

        return $payload;
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
        $data = $response->json();
        return is_array($data) ? ($data['id'] ?? null) : null;
    }
}
