<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'provider'    => 'sendgrid',
                'enabled'     => true,
                'priority'    => 1, // primario (tiene API de stats real)
                'daily_limit' => 100,
                'used_today'  => 0,
                'status'      => 'online',
                'config'      => json_encode([
                    'endpoint' => 'https://api.sendgrid.com/v3/mail/send',
                    'timeout'  => 15,
                ]),
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'provider'    => 'resend',
                'enabled'     => true,
                'priority'    => 2, // failover (sin API de stats; contador local únicamente)
                'daily_limit' => 100,
                'used_today'  => 0,
                'status'      => 'online',
                'config'      => json_encode([
                    'endpoint' => 'https://api.resend.com/emails',
                    'timeout'  => 15,
                ]),
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('gp_mail_providers')->updateOrInsert(
                ['provider' => $row['provider']],
                $row
            );
        }
    }

    public function down(): void
    {
        DB::table('gp_mail_providers')->whereIn('provider', ['resend', 'sendgrid'])->delete();
    }
};
