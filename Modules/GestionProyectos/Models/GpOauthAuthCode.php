<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\User\Models\User;

/**
 * Código de autorización OAuth: un solo uso y vida corta.
 *
 * Se guarda HASHEADO; el texto plano solo viaja en el redirect al cliente.
 */
class GpOauthAuthCode extends Model
{
    protected $table = 'gp_oauth_auth_codes';

    /** Vida del código. Corta a propósito: solo tiene que sobrevivir al redirect. */
    public const TTL_SECONDS = 60;

    protected $fillable = [
        'code_hash',
        'client_id',
        'user_id',
        'redirect_uri',
        'code_challenge',
        'code_challenge_method',
        'scopes',
        'resource',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'scopes'     => 'array',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Emite un código nuevo y devuelve [textoPlano, modelo]. */
    public static function issue(array $attrs): array
    {
        $plain = Str::random(64);

        $model = static::create(array_merge($attrs, [
            'code_hash'  => hash('sha256', $plain),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]));

        return [$plain, $model];
    }

    public static function findFromPlain(string $plain): ?self
    {
        return static::where('code_hash', hash('sha256', $plain))->first();
    }

    /** INVARIANTE 2: sin usar y sin vencer. */
    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * INVARIANTE 3: PKCE verificado de verdad.
     * base64url(sha256(verifier)) === code_challenge, en comparación constante.
     */
    public function verifyPkce(?string $verifier): bool
    {
        if ($verifier === null || $verifier === '') {
            return false;
        }

        if ($this->code_challenge_method !== 'S256') {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($this->code_challenge, $computed);
    }
}
