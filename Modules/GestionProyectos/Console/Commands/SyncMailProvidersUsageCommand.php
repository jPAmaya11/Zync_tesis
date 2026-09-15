<?php

namespace Modules\GestionProyectos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\GestionProyectos\Models\GpMailProvider;
use Modules\GestionProyectos\Services\Mail\MailProviderResolver;
use Throwable;

/**
 * Sincroniza used_today y used_this_month de cada provider contra los datos
 * reales expuestos por la API del provider (cuando aplica).
 *
 * Sólo afecta providers donde supportsUsageApi() = true. Hoy: SendGrid.
 * Resend queda sin tocar (su contador depende del orchestrator local).
 *
 * Uso:
 *   php artisan gp:mail:sync-usage                  → sincroniza todos los que aplican
 *   php artisan gp:mail:sync-usage --provider=sendgrid → sólo uno
 */
class SyncMailProvidersUsageCommand extends Command
{
    protected $signature = 'gp:mail:sync-usage
        {--provider= : Sincronizar sólo el provider con este nombre (ej. sendgrid)}';

    protected $description = 'Sincroniza los contadores de cuota (diario/mensual) con la API del provider cuando soporta usage API.';

    public function handle(MailProviderResolver $resolver): int
    {
        $query = GpMailProvider::query();
        if ($this->option('provider')) {
            $query->where('provider', $this->option('provider'));
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->warn('No hay providers que coincidan con los filtros.');
            return self::SUCCESS;
        }

        $synced = 0;
        foreach ($rows as $row) {
            try {
                $instance = $resolver->instantiate($row);
            } catch (Throwable $e) {
                $this->warn("Skip {$row->provider}: no se puede instanciar ({$e->getMessage()}).");
                continue;
            }

            if (!$instance->supportsUsageApi()) {
                $this->line("Skip {$row->provider}: no soporta usage API.");
                continue;
            }

            try {
                $stats = $instance->fetchUsageStats();
                if (!is_array($stats)) {
                    $this->warn("Skip {$row->provider}: fetchUsageStats devolvió null.");
                    continue;
                }

                $row->update([
                    'used_today'      => (int) ($stats['used_today'] ?? 0),
                    'used_this_month' => (int) ($stats['used_this_month'] ?? 0),
                    'last_sync_at'    => now(),
                ]);

                $row->refresh();

                // Si tras la sincronización ambas cuotas tienen margen y estaba
                // marcado como quota_exceeded, lo devolvemos a online.
                if ($row->status === GpMailProvider::STATUS_QUOTA_EXCEEDED && $row->hasQuotaAvailable()) {
                    $row->update(['status' => GpMailProvider::STATUS_ONLINE]);
                }

                $this->info("✓ {$row->provider}: used_today={$row->used_today}, used_this_month={$row->used_this_month}");
                $synced++;
            } catch (Throwable $e) {
                $row->update([
                    'last_error'    => mb_substr($e->getMessage(), 0, 2000),
                    'last_error_at' => now(),
                ]);
                $this->error("✗ {$row->provider}: {$e->getMessage()}");
                Log::warning('[gp:mail:sync-usage] error sincronizando provider', [
                    'provider' => $row->provider,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->line('');
        $this->info("Sincronización completa. Providers sincronizados: {$synced}");
        return self::SUCCESS;
    }
}
