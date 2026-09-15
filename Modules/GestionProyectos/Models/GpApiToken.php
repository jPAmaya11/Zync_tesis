<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\User\Models\User;

/**
 * Token de API para GestionProyectos.
 *
 * Formato del token plano entregado al usuario: "{id}|{secreto}".
 * En BD se guarda solo el sha256 del secreto → no es recuperable.
 */
class GpApiToken extends Model
{
    protected $table = 'gp_api_tokens';

    /** Vida del access token OAuth. */
    public const ACCESS_TTL_MINUTES = 60;

    /** Vida del refresh token OAuth (rotativo). */
    public const REFRESH_TTL_DAYS = 30;

    protected $fillable = [
        'user_id',
        'client_id',
        'name',
        'token',
        'abilities',
        'resource',
        'expires_at',
        'refresh_token_hash',
        'refresh_expires_at',
        'revoked_at',
        'last_used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'abilities'          => 'array',
        'last_used_at'       => 'datetime',
        'expires_at'         => 'datetime',
        'refresh_expires_at' => 'datetime',
        'revoked_at'         => 'datetime',
    ];

    protected $hidden = ['token', 'refresh_token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Crea un token para un usuario y devuelve [textoPlano, modelo].
     * El texto plano NO se vuelve a poder obtener (solo se muestra una vez).
     */
    public static function generate(int $userId, string $name = 'API Token', array $abilities = ['*']): array
    {
        $secret = Str::random(48);

        $model = static::create([
            'user_id'   => $userId,
            'name'      => $name !== '' ? $name : 'API Token',
            'token'     => hash('sha256', $secret),
            'abilities' => $abilities,
        ]);

        return [$model->id . '|' . $secret, $model];
    }

    /**
     * Resuelve un token plano ("{id}|{secreto}" o solo "{secreto}") al modelo,
     * comparando el hash de forma segura.
     */
    public static function findFromPlain(string $plain): ?self
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }

        if (str_contains($plain, '|')) {
            [$id, $secret] = explode('|', $plain, 2);
            if (!ctype_digit($id) || $secret === '') {
                return null;
            }
            $token = static::find((int) $id);
            if ($token && hash_equals($token->token, hash('sha256', $secret))) {
                return $token;
            }
            return null;
        }

        // Compatibilidad: buscar por hash directo si no viene el prefijo de id.
        return static::where('token', hash('sha256', $plain))->first();
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OAuth. Un token OAuth es un token normal CON client_id, caducidad y refresh;
    // uno manual deja esas columnas en NULL y se comporta exactamente igual que antes.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Emite el par access + refresh de una autorización OAuth.
     *
     * @return array{0: string, 1: string, 2: self} [accessPlano, refreshPlano, modelo]
     */
    public static function issueForOauth(
        int $userId,
        string $clientId,
        string $clientName,
        array $abilities,
        ?string $resource,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $accessSecret  = Str::random(48);
        $refreshSecret = Str::random(48);

        $model = static::create([
            'user_id'            => $userId,
            'client_id'          => $clientId,
            'name'               => $clientName !== '' ? $clientName : 'Cliente MCP',
            'token'              => hash('sha256', $accessSecret),
            'abilities'          => $abilities,
            'resource'           => $resource,
            'expires_at'         => now()->addMinutes(self::ACCESS_TTL_MINUTES),
            'refresh_token_hash' => hash('sha256', $refreshSecret),
            'refresh_expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
            'ip_address'         => $ip,
            'user_agent'         => $userAgent ? substr($userAgent, 0, 255) : null,
        ]);

        return [$model->id . '|' . $accessSecret, $model->id . '|' . $refreshSecret, $model];
    }

    /** Resuelve un refresh token plano ("{id}|{secreto}") a su modelo. */
    public static function findFromRefresh(string $plain): ?self
    {
        $plain = trim($plain);
        if (! str_contains($plain, '|')) {
            return null;
        }

        [$id, $secret] = explode('|', $plain, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = static::find((int) $id);

        if (! $token || $token->refresh_token_hash === null) {
            return null;
        }

        return hash_equals($token->refresh_token_hash, hash('sha256', $secret)) ? $token : null;
    }

    /**
     * INVARIANTE 4: rota el refresh token. Emite un access y un refresh nuevos e
     * invalida el anterior en la MISMA operación — obligatorio para clientes públicos.
     *
     * @return array{0: string, 1: string} [accessPlano, refreshPlano]
     */
    public function rotate(): array
    {
        $accessSecret  = Str::random(48);
        $refreshSecret = Str::random(48);

        $this->forceFill([
            'token'              => hash('sha256', $accessSecret),
            'expires_at'         => now()->addMinutes(self::ACCESS_TTL_MINUTES),
            'refresh_token_hash' => hash('sha256', $refreshSecret),
            'refresh_expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
            'last_used_at'       => now(),
        ])->save();

        return [$this->id . '|' . $accessSecret, $this->id . '|' . $refreshSecret];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Un token manual (expires_at NULL) no caduca nunca. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function refreshIsExpired(): bool
    {
        return $this->refresh_expires_at !== null && $this->refresh_expires_at->isPast();
    }

    /** Sirve para autenticar: ni revocado ni caducado. */
    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * INVARIANTE 6: el token solo vale para la audiencia con la que se emitió.
     * Los tokens manuales (resource NULL) no llevan restricción de audiencia.
     */
    public function allowsResource(string $resource): bool
    {
        if ($this->resource === null || $this->resource === '') {
            return true;
        }

        return hash_equals($this->resource, $resource);
    }

    /** Corta la sesión: access y refresh dejan de servir. */
    public function revoke(): void
    {
        $this->forceFill([
            'revoked_at'         => now(),
            'refresh_token_hash' => null,
        ])->save();
    }

    /** ¿Es una sesión OAuth (vs. un token manual de integración)? */
    public function isOauthSession(): bool
    {
        return $this->client_id !== null;
    }
}
