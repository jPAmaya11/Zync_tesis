<?php

namespace Modules\GestionProyectos\Services\Mail\Contracts;

use Modules\GestionProyectos\Services\Mail\DTO\MailMessageData;
use Modules\GestionProyectos\Services\Mail\Exceptions\MailProviderException;

interface MailProviderInterface
{
    /**
     * Nombre estable del provider (resend, sendgrid, ...). Coincide con gp_mail_providers.provider.
     */
    public function getName(): string;

    /**
     * Envía el correo. Devuelve array con datos crudos de la respuesta del proveedor
     * (al menos ['message_id' => ?, 'raw' => ?]).
     *
     * @throws MailProviderException si el envío falla por cualquier motivo.
     */
    public function send(MailMessageData $message): array;

    /**
     * Verifica configuración mínima (credenciales presentes, endpoint válido).
     */
    public function validate(): bool;

    /**
     * ¿El provider expone una API pública para consultar consumo real?
     * Si es true, fetchUsageStats() devuelve datos sincronizables desde el dashboard del provider.
     * Si es false (como Resend hoy), el contador local es la única fuente.
     */
    public function supportsUsageApi(): bool;

    /**
     * Cuando supportsUsageApi() = true, devuelve los contadores reales:
     *   ['used_today' => int, 'used_this_month' => int]
     * Cuando no aplica, retorna null.
     *
     * @throws MailProviderException si la llamada falla.
     */
    public function fetchUsageStats(): ?array;
}
