<?php

namespace Modules\GestionProyectos\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionProyectos\Models\GpMailProvider;

class ResetMailProvidersQuotaCommand extends Command
{
    protected $signature = 'gp:mail:reset-quota';
    protected $description = 'Resetea el contador used_today de todos los providers de correo (Resend, SendGrid, ...).';

    public function handle(): int
    {
        $providers = GpMailProvider::query()->get();
        foreach ($providers as $p) {
            $p->resetDailyQuota();
            $this->line("Reset {$p->provider}: used_today=0, status={$p->fresh()->status}");
        }
        return self::SUCCESS;
    }
}
