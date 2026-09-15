<?php

namespace Modules\GestionProyectos\Services\Mail;

use Illuminate\Contracts\Container\Container;
use Modules\GestionProyectos\Models\GpMailProvider;
use Modules\GestionProyectos\Services\Mail\Contracts\MailProviderInterface;
use Modules\GestionProyectos\Services\Mail\Providers\ResendProvider;
use Modules\GestionProyectos\Services\Mail\Providers\SendGridProvider;

/**
 * Resuelve qué provider debe usarse en cada intento, en orden de prioridad,
 * filtrando los que están deshabilitados, sin cuota o caídos.
 */
class MailProviderResolver
{
    /** Mapa de nombre de provider -> clase concreta. Agregar aquí nuevos providers. */
    private const PROVIDER_MAP = [
        'resend'   => ResendProvider::class,
        'sendgrid' => SendGridProvider::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * Devuelve los providers seleccionables ordenados por prioridad ASC.
     *
     * @return array<int, array{model: GpMailProvider, instance: MailProviderInterface}>
     */
    public function availableProviders(): array
    {
        $rows = GpMailProvider::query()
            ->where('enabled', true)
            ->orderBy('priority')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if (!isset(self::PROVIDER_MAP[$row->provider])) {
                continue;
            }
            if (!$row->isSelectable()) {
                continue;
            }

            $instance = $this->instantiate($row);
            if (!$instance->validate()) {
                continue;
            }

            $out[] = ['model' => $row, 'instance' => $instance];
        }

        return $out;
    }

    public function instantiate(GpMailProvider $row): MailProviderInterface
    {
        $class = self::PROVIDER_MAP[$row->provider]
            ?? throw new \RuntimeException("Provider desconocido: {$row->provider}");

        return new $class($row);
    }

    /** @return array<int, string> */
    public function knownProviderNames(): array
    {
        return array_keys(self::PROVIDER_MAP);
    }
}
