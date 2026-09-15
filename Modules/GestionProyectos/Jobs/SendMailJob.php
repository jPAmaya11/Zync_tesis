<?php

namespace Modules\GestionProyectos\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\AllProvidersFailedException;
use Modules\GestionProyectos\Services\Mail\Exceptions\NoProvidersAvailableException;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;

class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900]; // 1m, 5m, 15m

    public function __construct(public readonly array $messageData) {}

    public function handle(MailDispatcher $dispatcher): void
    {
        $message = new MailMessageData(
            to: $this->messageData['to'] ?? [],
            subject: (string) ($this->messageData['subject'] ?? ''),
            html: (string) ($this->messageData['html'] ?? ''),
            text: $this->messageData['text'] ?? null,
            meta: (array) ($this->messageData['meta'] ?? []),
        );

        try {
            $dispatcher->sendNow($message);
        } catch (NoProvidersAvailableException | AllProvidersFailedException $e) {
            // Ya quedó registrado en gp_mail_logs como failed_all + log critical.
            // No reintentar el Job: re-encolar no resolverá la indisponibilidad sistémica.
            $this->fail($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::critical('[SendMailJob] Falla terminal', [
            'error' => $e->getMessage(),
            'meta'  => $this->messageData['meta'] ?? [],
            'to'    => $this->messageData['to'] ?? [],
        ]);
    }
}
