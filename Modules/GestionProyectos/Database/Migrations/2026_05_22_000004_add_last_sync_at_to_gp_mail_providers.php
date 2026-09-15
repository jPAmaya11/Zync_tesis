<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna last_sync_at a gp_mail_providers para registrar cuándo fue
 * la última sincronización con la API del provider (sólo aplica a providers
 * que soporten usage API, ej. SendGrid). Y ejecuta un sync inicial best-effort
 * para que el panel tenga datos reales desde la primera carga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_mail_providers', function (Blueprint $table) {
            $table->timestamp('last_sync_at')->nullable()->after('last_month_reset_at');
        });

        // Sync inicial best-effort: si SendGrid responde, los contadores quedan
        // actualizados con el consumo real del mes. Si la API falla (red, key
        // inválida, etc.), la migración igual se completa.
        try {
            Artisan::call('gp:mail:sync-usage');
        } catch (\Throwable $e) {
            Log::warning('[migration] No se pudo ejecutar sync inicial de providers', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('gp_mail_providers', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
        });
    }
};
