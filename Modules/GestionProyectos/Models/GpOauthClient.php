<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Cliente OAuth registrado dinámicamente (DCR, RFC 7591).
 *
 * Claude registra un cliente nuevo en cada conexión fresca, así que esta tabla crece:
 * el GC limpia los que nunca se usaron. El consentimiento NO se recuerda por client_id
 * (ver GpOauthConsent) precisamente por eso.
 */
class GpOauthClient extends Model
{
    protected $table = 'gp_oauth_clients';

    protected $primaryKey = 'client_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'client_name',
        'redirect_uris',
        'grant_types',
        'token_endpoint_auth_method',
        'secret_hash',
        'last_used_at',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'grant_types'   => 'array',
        'last_used_at'  => 'datetime',
    ];

    protected $hidden = ['secret_hash'];

    /** Registra un cliente nuevo a partir del payload de /oauth/register. */
    public static function register(string $name, array $redirectUris, ?string $authMethod = null): self
    {
        return static::create([
            'client_id'                  => 'gpc_' . Str::random(32),
            'client_name'                => $name !== '' ? $name : 'Cliente MCP',
            'redirect_uris'              => array_values($redirectUris),
            'grant_types'                => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_method' => $authMethod ?: 'none',
        ]);
    }

    /**
     * ¿La redirect_uri solicitada está registrada?
     *
     * INVARIANTE 1: comparación EXACTA contra lo registrado; sin esto hay redirección
     * abierta. Única excepción, exigida por RFC 8252 §7.3: los redirects de loopback
     * (Claude Code usa un puerto efímero distinto en cada sesión) se comparan ignorando
     * el puerto. Se aplica también a "localhost" porque es lo que Claude Code declara.
     */
    public function allowsRedirectUri(string $uri): bool
    {
        foreach ($this->redirect_uris ?? [] as $registered) {
            if (hash_equals($registered, $uri)) {
                return true;
            }

            if (self::isLoopback($registered) && self::isLoopback($uri)
                && self::withoutPort($registered) === self::withoutPort($uri)) {
                return true;
            }
        }

        return false;
    }

    private static function isLoopback(string $uri): bool
    {
        $host = parse_url($uri, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /** Normaliza un loopback a esquema+host+path, descartando el puerto. */
    private static function withoutPort(string $uri): string
    {
        $p = parse_url($uri);

        return ($p['scheme'] ?? '') . '://' . ($p['host'] ?? '') . ($p['path'] ?? '');
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
