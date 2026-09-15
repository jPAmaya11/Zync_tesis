<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas del authorization server OAuth 2.1 propio que autentica el MCP.
 *
 * Se implementa a mano (sin Passport ni ninguna dependencia nueva): el helper
 * Mcp::oauthRoutes() de laravel/mcp hardcodea Passport y además solo publica los
 * .well-known y /register — no trae /authorize ni /token. Ver docs/PLAN-OAUTH-MCP.md.
 *
 * Los ACCESS TOKENS no viven aquí: reusan gp_api_tokens (ver la migración siguiente),
 * para que el middleware siga resolviendo un único tipo de credencial.
 *
 * IDEMPOTENTE: cada create va bajo hasTable(), así que re-ejecutarla no falla. Producción
 * ya tiene datos y el migrate no puede reventar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Clientes registrados dinámicamente (DCR, RFC 7591) ─────────────────
        // Claude registra un cliente nuevo en cada conexión fresca; por eso el
        // consentimiento NO se recuerda por client_id (ver gp_oauth_consents).
        if (! Schema::hasTable('gp_oauth_clients')) {
            Schema::create('gp_oauth_clients', function (Blueprint $table) {
                $table->string('client_id', 80)->primary();
                $table->string('client_name', 150)
                      ->comment('Nombre declarado por el cliente, ej. "Claude"');
                $table->json('redirect_uris')
                      ->comment('URIs exactas; la validación es por comparación exacta');
                $table->json('grant_types')->nullable();
                $table->string('token_endpoint_auth_method', 40)->default('none')
                      ->comment('"none" = cliente público (Claude lo es vía DCR y CIMD)');
                $table->string('secret_hash', 64)->nullable()
                      ->comment('Solo para clientes confidenciales; sha256');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('client_name');
                $table->index('last_used_at');
            });
        }

        // ── Códigos de autorización (un solo uso, vida corta) ──────────────────
        if (! Schema::hasTable('gp_oauth_auth_codes')) {
            Schema::create('gp_oauth_auth_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code_hash', 64)->unique()
                      ->comment('sha256 del código; el texto plano no se guarda');
                $table->string('client_id', 80)->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('redirect_uri', 500);
                $table->string('code_challenge', 128)
                      ->comment('PKCE: se verifica base64url(sha256(verifier)) === challenge');
                $table->string('code_challenge_method', 10)->default('S256');
                $table->json('scopes')->nullable();
                $table->string('resource', 500)->nullable()
                      ->comment('Audiencia solicitada (RFC 8707): URL canónica del MCP');
                $table->timestamp('expires_at')
                      ->comment('Vida corta (~60s)');
                $table->timestamp('used_at')->nullable()
                      ->comment('Se marca al canjear, dentro de la misma transacción');
                $table->timestamps();

                $table->index('expires_at');
            });
        }

        // ── Consentimiento recordado ──────────────────────────────────────────
        // Clave por usuario + NOMBRE de cliente (no client_id): con DCR el client_id
        // cambia en cada conexión, así que por client_id volvería a preguntar siempre.
        if (! Schema::hasTable('gp_oauth_consents')) {
            Schema::create('gp_oauth_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('client_name', 150);
                $table->json('scopes')->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'client_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_oauth_consents');
        Schema::dropIfExists('gp_oauth_auth_codes');
        Schema::dropIfExists('gp_oauth_clients');
    }
};
