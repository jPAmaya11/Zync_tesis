<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill del contador used_this_month en gp_mail_providers.
 *
 * Cuenta los envíos exitosos (status=sent) del mes en curso desde gp_mail_logs
 * agrupados por provider y los aplica como punto de partida. Se ejecuta una sola
 * vez para alinear el contador con el consumo real previo a esta funcionalidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gp_mail_logs') || !Schema::hasTable('gp_mail_providers')) {
            return;
        }
        if (!Schema::hasColumn('gp_mail_providers', 'used_this_month')) {
            return;
        }

        // Rango del mes actual en zona horaria America/Lima → convertido a UTC para casar con created_at de la BD.
        $startOfMonth = now('America/Lima')->startOfMonth()->utc();
        $endOfMonth   = now('America/Lima')->endOfMonth()->utc();

        $counts = DB::table('gp_mail_logs')
            ->where('status', 'sent')
            ->whereNotNull('provider')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('provider')
            ->select('provider', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'provider');

        foreach ($counts as $providerName => $cnt) {
            DB::table('gp_mail_providers')
                ->where('provider', $providerName)
                ->update(['used_this_month' => (int) $cnt]);
        }
    }

    public function down(): void
    {
        // Reset suave de los contadores aplicados por este backfill.
        // No hay forma confiable de revertir solo lo que esta migración sumó,
        // así que dejamos used_this_month en 0 para evitar inconsistencias.
        if (Schema::hasColumn('gp_mail_providers', 'used_this_month')) {
            DB::table('gp_mail_providers')->update(['used_this_month' => 0]);
        }
    }
};
