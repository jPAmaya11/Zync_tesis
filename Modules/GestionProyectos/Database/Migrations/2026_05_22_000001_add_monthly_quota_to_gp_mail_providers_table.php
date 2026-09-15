<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_mail_providers', function (Blueprint $table) {
            $table->unsignedInteger('monthly_limit')->default(3000)->after('daily_limit');
            $table->unsignedInteger('used_this_month')->default(0)->after('used_today');
            $table->timestamp('last_month_reset_at')->nullable()->after('last_reset_at');
        });

        // Establecer límite por defecto en providers ya existentes (Resend + SendGrid free tier = 3000/mes)
        DB::table('gp_mail_providers')
            ->whereIn('provider', ['resend', 'sendgrid'])
            ->update(['monthly_limit' => 3000]);

        // Backfill: contar los envíos exitosos del MES en curso (America/Lima)
        // para que used_this_month refleje el consumo real desde el inicio,
        // no quede en 0 cuando ya hubo envíos previos a esta migración.
        $startOfMonth = now('America/Lima')->startOfMonth()->utc();
        $endOfMonth   = now('America/Lima')->endOfMonth()->utc();

        if (Schema::hasTable('gp_mail_logs')) {
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
    }

    public function down(): void
    {
        Schema::table('gp_mail_providers', function (Blueprint $table) {
            $table->dropColumn(['monthly_limit', 'used_this_month', 'last_month_reset_at']);
        });
    }
};
