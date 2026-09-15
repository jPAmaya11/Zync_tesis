<?php

namespace Modules\GestionProyectos\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Observers\ProyectoObserver;
use Modules\GestionProyectos\Policies\GestionProyectosPolicy;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\GestionProyectos\Services\CustomFieldService;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;
use Modules\GestionProyectos\Services\Mail\MailProviderResolver;
use Modules\GestionProyectos\Services\ProyectoService;

class GestionProyectosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/gestion-proyectos.php',
            'gestion-proyectos'
        );

        $this->app->bind(
            ProyectoServiceInterface::class,
            ProyectoService::class
        );

        $this->app->bind(
            CustomFieldServiceInterface::class,
            CustomFieldService::class
        );

        // ── Mail Orchestrator (Resend → SendGrid, failover + cuotas) ──────────
        $this->app->singleton(MailProviderResolver::class);
        $this->app->singleton(MailDispatcher::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/ai.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'gestion-proyectos');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\GestionProyectos\Console\Commands\ResetMailProvidersQuotaCommand::class,
                \Modules\GestionProyectos\Console\Commands\ResetMailProvidersMonthlyQuotaCommand::class,
                \Modules\GestionProyectos\Console\Commands\SyncMailProvidersUsageCommand::class,
                \Modules\GestionProyectos\Console\Commands\SlaCheckCommand::class,
                \Modules\GestionProyectos\Console\Commands\OauthGcCommand::class,
            ]);
        }

        // Observers de auditoría y sincronización
        Proyecto::observe(ProyectoObserver::class);
        Proyecto::observe(\Modules\GestionProyectos\Observers\ProyectoNotificationObserver::class);
        \Modules\GestionProyectos\Models\GpProject::observe(\Modules\GestionProyectos\Observers\GpProjectObserver::class);
        \Modules\GestionProyectos\Models\GpSpaceMember::observe(\Modules\GestionProyectos\Observers\GpSpaceMemberObserver::class);
        \Modules\GestionProyectos\Models\GpCustomFieldValue::observe(\Modules\GestionProyectos\Observers\GpCustomFieldValueObserver::class);
        \Modules\GestionProyectos\Models\GpActivityHistory::observe(\Modules\GestionProyectos\Observers\GpActivityHistoryObserver::class);

        // ── Gates / Policies ─────────────────────────────────────────────────
        // Gate simple: acceso al módulo
        Gate::define('gestion-proyectos.ver',   [GestionProyectosPolicy::class, 'ver']);
        // Gate simple: bypass total para administradores globales
        Gate::define('gestion-proyectos.admin', fn ($user) =>
            $user->hasRole('admin') || $user->hasPermissionTo('gestion-proyectos.admin')
        );
        // Gates con argumento string (projectKey)
        Gate::define('crear',              [GestionProyectosPolicy::class, 'crear']);
        Gate::define('manage',             [GestionProyectosPolicy::class, 'manage']);
        Gate::define('registrarHistorial', [GestionProyectosPolicy::class, 'registrarHistorial']);
        // Policy basada en modelo Proyecto (para update/eliminar)
        Gate::policy(Proyecto::class, GestionProyectosPolicy::class);
    }
}
