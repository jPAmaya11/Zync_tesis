<?php

namespace Modules\GestionProyectos\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionProyectos\Models\GpMailProvider;

class ResetMailProvidersMonthlyQuotaCommand extends Command
{
    protected $signature = 'gp:mail:reset-monthly-quota';
    protected $description = 'Resetea el contador used_this_month de todos los providers de correo. Programado para el día 1 de cada mes a las 00:00 America/Lima.';

    public function handle(): int
    {
        $providers = GpMailProvider::query()->get();
        foreach ($providers as $p) {
            $p->resetMonthlyQuota();
            $this->line("Reset mensual {$p->provider}: used_this_month=0, status={$p->fresh()->status}");
        }
        return self::SUCCESS;
    }
}
