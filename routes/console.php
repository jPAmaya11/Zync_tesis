<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas (Laravel 12: el scheduler se define AQUÍ)
|--------------------------------------------------------------------------
| Importante: en este proyecto bootstrap/app.php usa withKernels(), que
| ignora App\Console\Kernel, por lo que el schedule() de ese Kernel NUNCA
| corre. La facade Schedule registra sobre la instancia que sí leen
| `schedule:run` / `schedule:list`. Con el cron del sistema apuntando a
| `php artisan schedule:run` cada minuto, basta declarar las tareas aquí.
*/

// GESTIÓN DE PROYECTOS — Recolector del authorization server OAuth del MCP, a las
// 04:00 Lima. Limpia códigos de autorización vencidos o ya usados, clientes que se
// registraron (DCR) y nunca se usaron, y sesiones muertas hace más de 7 días. Esos
// 7 días son deliberados: durante la ventana el panel de Sesiones MCP las sigue
// mostrando como "expirada" para que el usuario entienda por qué se le cortó, en vez
// de que desaparezcan sin explicación. Nunca toca los tokens manuales de integración
// (client_id NULL), que son los del chatbot y no caducan.
Schedule::command('gp:oauth-gc')
    ->dailyAt('04:00')
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/gp-oauth-gc.log'));

// GESTIÓN DE PROYECTOS — Reset diario de cuotas de providers de correo, a las 00:00
// hora Lima para alinearse con el día calendario local.
Schedule::command('gp:mail:reset-quota')
    ->dailyAt('00:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(2)
    ->appendOutputTo(storage_path('logs/gp-mail-reset.log'));

// GESTIÓN DE PROYECTOS — Reset mensual de cuotas, el día 1 de cada mes a las 00:00 Lima.
Schedule::command('gp:mail:reset-monthly-quota')
    ->monthlyOn(1, '00:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/gp-mail-reset-monthly.log'));

// GESTIÓN DE PROYECTOS — Sync de cuotas contra la API del provider (SendGrid /v3/stats).
// Cada hora entre 06:00 y 22:00 Lima: frescura razonable sin saturar la API.
Schedule::command('gp:mail:sync-usage')
    ->hourly()
    ->between('06:00', '22:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/gp-mail-sync-usage.log'));

// GESTIÓN DE PROYECTOS — Alertas de SLA / vencimiento cercano de tareas.
Schedule::command('gp:mail:sla-check')
    ->everyThirtyMinutes()
    ->between('07:00', '20:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/gp-mail-sla.log'));
