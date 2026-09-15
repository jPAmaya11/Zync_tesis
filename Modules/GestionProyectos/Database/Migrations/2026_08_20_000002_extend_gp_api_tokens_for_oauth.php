<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende gp_api_tokens para que sirva también como almacén de tokens OAuth.
 *
 * Decisión de diseño: NO se crea una tabla de tokens paralela. El middleware sigue
 * resolviendo una sola credencial; un token OAuth es simplemente un token con
 * client_id, expiración y refresh. Un token manual queda con esas columnas en NULL.
 *
 * SEGURIDAD DEL MIGRATE EN PRODUCCIÓN
 * -----------------------------------
 * La migración original de gp_api_tokens NO se toca (ya corrió y está registrada;
 * editarla haría divergir producción de las instalaciones nuevas). Todo va aquí, es
 * ADITIVO y cada columna se añade bajo hasColumn(): re-ejecutarla o aplicarla a medias
 * no falla. El peor caso es que no haga nada.
 *
 * ⚠️ REVOCACIÓN DE TOKENS HEREDADOS
 * Se revocan TODOS los tokens anteriores (client_id NULL) por decisión explícita: se
 * arranca limpio con OAuth. Esto DESCONECTA al chatbot de WhatsApp hasta que un admin
 * le genere un token nuevo desde la web (la generación manual se conserva justo para
 * eso). Hay que rehacerlo en el mismo momento del despliegue. Ver docs/PLAN-OAUTH-MCP.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_api_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('gp_api_tokens', 'client_id')) {
                $table->string('client_id', 80)->nullable()->after('user_id')
                      ->comment('Cliente OAuth que lo obtuvo. NULL = token manual (integraciones)');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'resource')) {
                $table->string('resource', 500)->nullable()->after('abilities')
                      ->comment('Audiencia (RFC 8707). NULL = token manual, sin restricción de audiencia');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('resource')
                      ->comment('Access token: 1 hora. NULL = no caduca (token manual)');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'refresh_token_hash')) {
                $table->string('refresh_token_hash', 64)->nullable()->unique()->after('expires_at')
                      ->comment('sha256 del refresh. Se ROTA: cada uso emite uno nuevo');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'refresh_expires_at')) {
                $table->timestamp('refresh_expires_at')->nullable()->after('refresh_token_hash')
                      ->comment('Refresh token: 30 días');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('refresh_expires_at')
                      ->comment('Desconexión desde el panel de Sesiones MCP, o revocación masiva');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('last_used_at')
                      ->comment('Para que el usuario reconozca sus sesiones en el panel');
            }

            if (! Schema::hasColumn('gp_api_tokens', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('ip_address');
            }
        });

        // Índice de apoyo para el listado de sesiones del usuario.
        if (Schema::hasColumn('gp_api_tokens', 'revoked_at')) {
            $indexes = collect(DB::select('SHOW INDEX FROM gp_api_tokens'))
                ->pluck('Key_name')
                ->unique();

            if (! $indexes->contains('gp_api_tokens_user_id_revoked_at_index')) {
                Schema::table('gp_api_tokens', function (Blueprint $table) {
                    $table->index(['user_id', 'revoked_at']);
                });
            }
        }

        // Revocación de los tokens heredados: se arranca limpio con OAuth.
        // Si la tabla está vacía afecta a 0 filas y no pasa nada.
        DB::table('gp_api_tokens')
            ->whereNull('client_id')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('gp_api_tokens', function (Blueprint $table) {
            foreach ([
                'client_id', 'resource', 'expires_at', 'refresh_token_hash',
                'refresh_expires_at', 'revoked_at', 'ip_address', 'user_agent',
            ] as $column) {
                if (Schema::hasColumn('gp_api_tokens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
