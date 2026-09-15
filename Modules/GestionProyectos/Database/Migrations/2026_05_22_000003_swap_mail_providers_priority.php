<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invierte prioridades de los providers de correo:
 *   SendGrid pasa a 1 (primary, tiene API de stats real)
 *   Resend pasa a 2 (failover, sin API de stats)
 *
 * Por qué este cambio: SendGrid expone /v3/stats que permite sincronizar el
 * consumo real desde el provider. Resend no tiene endpoint equivalente, por
 * lo que su contador depende sólo de los envíos que el orchestrator hace.
 * Tener SendGrid como primario garantiza datos visibles confiables.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gp_mail_providers')->where('provider', 'sendgrid')->update(['priority' => 1]);
        DB::table('gp_mail_providers')->where('provider', 'resend')->update(['priority' => 2]);
    }

    public function down(): void
    {
        DB::table('gp_mail_providers')->where('provider', 'resend')->update(['priority' => 1]);
        DB::table('gp_mail_providers')->where('provider', 'sendgrid')->update(['priority' => 2]);
    }
};
