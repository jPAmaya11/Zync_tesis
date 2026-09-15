<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Consentimiento recordado, para que el usuario no tenga que aprobar cada reconexión.
 *
 * Se guarda por usuario + NOMBRE de cliente, no por client_id: con DCR el client_id
 * cambia en cada conexión fresca, así que por client_id volveríamos a preguntar siempre.
 *
 * Al desconectar una sesión desde el panel hay que BORRAR también el consentimiento; si
 * no, el cliente rehace el flujo, lo encuentra recordado y se reconecta solo — el usuario
 * creería haber cortado sin haber cortado.
 */
class GpOauthConsent extends Model
{
    protected $table = 'gp_oauth_consents';

    protected $fillable = ['user_id', 'client_name', 'scopes', 'granted_at'];

    protected $casts = [
        'scopes'     => 'array',
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function recordar(int $userId, string $clientName, array $scopes): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'client_name' => $clientName],
            ['scopes' => $scopes, 'granted_at' => now()],
        );
    }

    public static function yaConcedido(int $userId, string $clientName): bool
    {
        return static::where('user_id', $userId)
            ->where('client_name', $clientName)
            ->exists();
    }

    public static function olvidar(int $userId, string $clientName): void
    {
        static::where('user_id', $userId)->where('client_name', $clientName)->delete();
    }
}
