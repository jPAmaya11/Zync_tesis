<?php

namespace Modules\GestionProyectos\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\GestionProyectos\Models\GpApiToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica peticiones a la API de GestionProyectos vía token Bearer.
 *
 * Resuelve el token (tabla gp_api_tokens), carga al usuario dueño y lo deja
 * como usuario autenticado del request → a partir de ahí aplican TODAS las
 * autorizaciones existentes (Gate gestion-proyectos.ver, policies, roles por
 * espacio GpSpaceMember, permisos Spatie). No usa Sanctum ni la sesión web.
 *
 * LÍNEA DE CORTE DE OAuth: lo único que cambió al añadir OAuth es de dónde sale el
 * token y su ciclo de vida (caducidad, revocación, audiencia). Después de
 * Auth::setUser() no hay ni una diferencia: el MCP sigue haciendo exactamente lo
 * mismo que hacía, con los mismos roles y las mismas reglas de estado.
 *
 * Los tokens MANUALES (client_id NULL) siguen funcionando igual que siempre: no
 * caducan y no llevan restricción de audiencia. Es lo que usan las integraciones
 * servidor-a-servidor, que no pueden hacer un flujo OAuth interactivo.
 */
class AuthGpApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return $this->noAutorizado('Token de API no provisto. Enviá el header Authorization: Bearer <token>.');
        }

        $token = GpApiToken::findFromPlain($bearer);

        if (!$token) {
            return $this->noAutorizado('Token de API inválido o revocado.');
        }

        if ($token->isRevoked()) {
            return $this->noAutorizado('La sesión fue desconectada. Volvé a conectar el cliente.');
        }

        if ($token->isExpired()) {
            return $this->noAutorizado('El token expiró. Refrescá la sesión.');
        }

        // INVARIANTE 6 (RFC 8707): el token solo vale para la audiencia con la que se
        // emitió. Sin esto, un token de otro recurso del mismo dominio entraría acá.
        if (!$token->allowsResource($this->resourceCanonico())) {
            return $this->noAutorizado('El token no fue emitido para este servidor MCP.');
        }

        $user = $token->user;

        if (!$user) {
            return $this->noAutorizado('El usuario asociado al token ya no existe.');
        }

        // Gate de acceso al módulo (mismo criterio que la web).
        if (!$user->hasRole('admin') && !$user->can('gestion-proyectos.ver')) {
            return response()->json([
                'message' => 'El usuario no tiene permiso de acceso a Gestión de Proyectos.',
            ], 403);
        }

        // Dejar al usuario como autenticado → policies / $request->user() / auth() funcionan.
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Exponer el token actual por si el controller necesita sus abilities.
        $request->attributes->set('gp_api_token', $token);

        $token->touchLastUsed();

        return $next($request);
    }

    /**
     * 401 con el puntero al metadata del recurso (RFC 9728 §5.1).
     *
     * ESTA CABECERA ES EL ARRANQUE DE TODO EL FLUJO OAuth: es como el cliente
     * descubre dónde está el authorization server. Sin ella se queda sin saber
     * adónde ir y la conexión falla con un "no se pudo alcanzar el servidor".
     * Tiene que ir en un 401 — en un 200 el cliente no la mira.
     */
    private function noAutorizado(string $mensaje): Response
    {
        // Se arma desde APP_URL, no con url(): tiene que apuntar al mismo dominio
        // canónico que anuncia el metadata, entre por donde entre la petición.
        $metadata = rtrim(config('gestion-proyectos.oauth.issuer'), '/') . '/.well-known/oauth-protected-resource/'
            . trim(config('gestion-proyectos.oauth.resource_path', 'mcp/gestion-proyectos'), '/');

        return response()->json(['message' => $mensaje], 401)
            ->header('WWW-Authenticate', sprintf(
                'Bearer resource_metadata="%s", scope="%s"',
                $metadata,
                implode(' ', config('gestion-proyectos.oauth.scopes_supported', ['mcp'])),
            ));
    }

    /** URL canónica de este MCP, la audiencia válida de sus tokens. */
    private function resourceCanonico(): string
    {
        return rtrim(config('gestion-proyectos.oauth.issuer'), '/') . '/'
            . trim(config('gestion-proyectos.oauth.resource_path', 'mcp/gestion-proyectos'), '/');
    }
}
