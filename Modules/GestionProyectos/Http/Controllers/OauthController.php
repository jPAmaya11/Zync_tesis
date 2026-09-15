<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpApiToken;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpOauthAuthCode;
use Modules\GestionProyectos\Models\GpOauthClient;
use Modules\GestionProyectos\Models\GpOauthConsent;

/**
 * Authorization server OAuth 2.1 del servidor MCP.
 *
 * Implementación propia, sin Passport: el helper Mcp::oauthRoutes() de laravel/mcp
 * hardcodea Passport y solo publica los .well-known y /register — no trae /authorize
 * ni /token. Ver docs/PLAN-OAUTH-MCP.md.
 *
 * Los invariantes numerados del plan están marcados en el código donde se aplican.
 * No relajarlos: cada uno es un fallo de seguridad conocido.
 */
class OauthController extends Controller
{
    /**
     * URL canónica del MCP. Debe coincidir CARÁCTER POR CARÁCTER con la URL que el
     * usuario pega en el cliente, así que se arma desde gestion-proyectos.oauth.issuer
     * y NO con url(): url() toma el host de la petición entrante, y bastaría con
     * entrar por otro host o por la IP para que el metadata dejara de cuadrar con el
     * issuer y el cliente lo rechazara. Todo el descubrimiento tiene que hablar de la
     * misma URL.
     */
    private function resource(): string
    {
        return $this->issuer() . '/' . trim(config('gestion-proyectos.oauth.resource_path', 'mcp/gestion-proyectos'), '/');
    }

    private function issuer(): string
    {
        return rtrim(config('gestion-proyectos.oauth.issuer'), '/');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Descubrimiento
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * RFC 9728. Es lo que apunta el WWW-Authenticate del 401 y lo que arranca todo
     * el flujo. El campo "resource" tiene que ser la URL exacta del MCP.
     */
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource'              => $this->resource(),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported'      => config('gestion-proyectos.oauth.scopes_supported'),
            'bearer_methods_supported' => ['header'],
        ]);
    }

    /**
     * RFC 8414. code_challenge_methods_supported = ["S256"] es OBLIGATORIO: los
     * clientes lo comprueban antes de arrancar. token_endpoint_auth_method "none"
     * porque Claude es cliente público (tanto por DCR como por CIMD).
     */
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer'                                => $this->issuer(),
            'authorization_endpoint'                => $this->issuer() . '/oauth/authorize',
            'token_endpoint'                        => $this->issuer() . '/oauth/token',
            'registration_endpoint'                 => $this->issuer() . '/oauth/register',
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported'                      => config('gestion-proyectos.oauth.scopes_supported'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registro dinámico de clientes (RFC 7591) — body JSON
    // ─────────────────────────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_name'                => ['nullable', 'string', 'max:150'],
            'redirect_uris'              => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*'            => ['required', 'string', 'max:500'],
            'token_endpoint_auth_method' => ['nullable', 'string', 'max:40'],
        ]);

        foreach ($data['redirect_uris'] as $uri) {
            if (! $this->redirectUriEsAceptable($uri)) {
                return response()->json([
                    'error'             => 'invalid_redirect_uri',
                    'error_description' => "redirect_uri no permitida: {$uri}. Debe ser https o loopback.",
                ], 400);
            }
        }

        $client = GpOauthClient::register(
            $data['client_name'] ?? 'Cliente MCP',
            $data['redirect_uris'],
            $data['token_endpoint_auth_method'] ?? null,
        );

        return response()->json([
            'client_id'                  => $client->client_id,
            'client_name'                => $client->client_name,
            'redirect_uris'              => $client->redirect_uris,
            'grant_types'                => $client->grant_types,
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => $client->token_endpoint_auth_method,
            'client_id_issued_at'        => $client->created_at->timestamp,
        ], 201);
    }

    /** Solo HTTPS o loopback (OAuth 2.1 §1.5 y RFC 8252). */
    private function redirectUriEsAceptable(string $uri): bool
    {
        $parts = parse_url($uri);

        if (! $parts || empty($parts['scheme']) || empty($parts['host']) || isset($parts['fragment'])) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http'
            && in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Autorización (con sesión web: el middleware 'auth' manda al login existente)
    // ─────────────────────────────────────────────────────────────────────────

    public function authorize(Request $request)
    {
        $validacion = $this->validarPeticionDeAutorizacion($request);

        if ($this->esError($validacion)) {
            return response()->view('gestion-proyectos::oauth.error', $validacion, 400);
        }

        [$client, $params] = $validacion;

        $user = $request->user();

        // Gate del módulo: el mismo criterio que la web y que el middleware del MCP.
        if (! $user->hasRole('admin') && ! $user->can('gestion-proyectos.ver')) {
            return response()->view('gestion-proyectos::oauth.error', [
                'titulo'  => 'Sin acceso a Gestión de Proyectos',
                'mensaje' => 'Tu usuario no tiene permiso para usar este módulo. Pedí acceso a un administrador.',
            ], 403);
        }

        // Consentimiento recordado: se salta la pantalla y se redirige directo.
        if (GpOauthConsent::yaConcedido($user->id, $client->client_name)) {
            return $this->emitirCodigoYRedirigir($request, $client, $params);
        }

        return response()->view('gestion-proyectos::oauth.consent', [
            'client'  => $client,
            'user'    => $user,
            'params'  => $params,
            'scopes'  => $params['scopes'],
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validacion = $this->validarPeticionDeAutorizacion($request);

        if ($this->esError($validacion)) {
            abort(400, $validacion['mensaje']);
        }

        [$client, $params] = $validacion;

        $user = $request->user();

        if (! $user->hasRole('admin') && ! $user->can('gestion-proyectos.ver')) {
            abort(403, 'Tu usuario no tiene permiso para usar Gestión de Proyectos.');
        }

        if ($request->input('accion') === 'denegar') {
            return redirect()->away($this->urlConParametros($params['redirect_uri'], [
                'error'             => 'access_denied',
                'error_description' => 'El usuario denegó la solicitud.',
                'state'             => $params['state'],
            ]));
        }

        GpOauthConsent::recordar($user->id, $client->client_name, $params['scopes']);

        return $this->emitirCodigoYRedirigir($request, $client, $params);
    }

    private function emitirCodigoYRedirigir(Request $request, GpOauthClient $client, array $params): RedirectResponse
    {
        [$plain] = GpOauthAuthCode::issue([
            'client_id'             => $client->client_id,
            'user_id'               => $request->user()->id,
            'redirect_uri'          => $params['redirect_uri'],
            'code_challenge'        => $params['code_challenge'],
            'code_challenge_method' => 'S256',
            'scopes'                => $params['scopes'],
            'resource'              => $params['resource'],
        ]);

        $client->touchLastUsed();

        return redirect()->away($this->urlConParametros($params['redirect_uri'], [
            'code'  => $plain,
            'state' => $params['state'],
        ]));
    }

    /**
     * ¿La validación devolvió un error a mostrar, en vez de [client, params]?
     *
     * Ambos casos son arrays, así que NO se pueden distinguir con is_array(): el error
     * se reconoce por sus claves. El éxito es una lista de dos posiciones.
     */
    private function esError(array $validacion): bool
    {
        return array_key_exists('mensaje', $validacion);
    }

    /**
     * Valida la petición de /authorize. Devuelve [client, params] si está bien, o un
     * array con titulo/mensaje si hay que mostrar un error (ver esError()).
     *
     * Los errores de client_id o redirect_uri NO se redirigen (no hay destino en el que
     * confiar): se muestran en pantalla, como manda OAuth 2.1 §7.12.
     */
    private function validarPeticionDeAutorizacion(Request $request): array
    {
        $clientId = (string) $request->input('client_id', '');
        $client   = GpOauthClient::find($clientId);

        if (! $client) {
            return ['titulo' => 'Cliente no reconocido', 'mensaje' => 'El client_id no está registrado.'];
        }

        $redirectUri = (string) $request->input('redirect_uri', '');

        // INVARIANTE 1: comparación exacta contra lo registrado.
        if (! $client->allowsRedirectUri($redirectUri)) {
            return [
                'titulo'  => 'URL de retorno no autorizada',
                'mensaje' => 'La redirect_uri no coincide con ninguna de las registradas por el cliente.',
            ];
        }

        if ($request->input('response_type') !== 'code') {
            return ['titulo' => 'Tipo de respuesta no soportado', 'mensaje' => 'Solo se admite response_type=code.'];
        }

        $challenge = (string) $request->input('code_challenge', '');
        $method    = (string) $request->input('code_challenge_method', '');

        // INVARIANTE 3: sin PKCE S256 no se arranca.
        if ($challenge === '' || $method !== 'S256') {
            return [
                'titulo'  => 'Falta PKCE',
                'mensaje' => 'Esta autorización exige code_challenge con code_challenge_method=S256.',
            ];
        }

        $scopeSolicitado = (string) $request->input('scope', '');
        $soportados      = config('gestion-proyectos.oauth.scopes_supported', []);
        $scopes          = array_values(array_intersect(
            preg_split('/\s+/', trim($scopeSolicitado)) ?: [],
            $soportados,
        ));

        if (empty($scopes)) {
            $scopes = [config('gestion-proyectos.oauth.scope', 'mcp')];
        }

        return [$client, [
            'redirect_uri'   => $redirectUri,
            'state'          => $request->input('state'),
            'code_challenge' => $challenge,
            'scopes'         => $scopes,
            'resource'       => $request->input('resource') ?: $this->resource(),
        ]];
    }

    private function urlConParametros(string $base, array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Emisión de tokens — body form-urlencoded
    // ─────────────────────────────────────────────────────────────────────────

    public function token(Request $request): JsonResponse
    {
        return match ($request->input('grant_type')) {
            'authorization_code' => $this->grantAuthorizationCode($request),
            'refresh_token'      => $this->grantRefreshToken($request),
            default              => $this->errorOauth('unsupported_grant_type', 'grant_type no soportado.'),
        };
    }

    private function grantAuthorizationCode(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');

        if ($code === '') {
            return $this->errorOauth('invalid_request', 'Falta el parámetro code.');
        }

        $authCode = GpOauthAuthCode::findFromPlain($code);

        if (! $authCode) {
            return $this->errorOauth('invalid_grant', 'Código de autorización inválido.');
        }

        // INVARIANTE 2: un solo uso, dentro de la misma transacción que lo canjea.
        $marcado = DB::transaction(function () use ($authCode) {
            $fresco = GpOauthAuthCode::whereKey($authCode->id)->lockForUpdate()->first();

            if (! $fresco || ! $fresco->isUsable()) {
                return null;
            }

            $fresco->forceFill(['used_at' => now()])->save();

            return $fresco;
        });

        if (! $marcado) {
            return $this->errorOauth('invalid_grant', 'El código ya fue usado o expiró.');
        }

        if (! hash_equals($marcado->client_id, (string) $request->input('client_id', ''))) {
            return $this->errorOauth('invalid_grant', 'El código no pertenece a ese cliente.');
        }

        if (! hash_equals($marcado->redirect_uri, (string) $request->input('redirect_uri', ''))) {
            return $this->errorOauth('invalid_grant', 'La redirect_uri no coincide con la de la autorización.');
        }

        // INVARIANTE 3: PKCE verificado de verdad.
        if (! $marcado->verifyPkce($request->input('code_verifier'))) {
            return $this->errorOauth('invalid_grant', 'Verificación PKCE fallida.');
        }

        $client = GpOauthClient::find($marcado->client_id);

        [$access, $refresh, $token] = GpApiToken::issueForOauth(
            userId:     $marcado->user_id,
            clientId:   $marcado->client_id,
            clientName: $client?->client_name ?? 'Cliente MCP',
            abilities:  config('gestion-proyectos.oauth.abilities', ['*']),
            resource:   $marcado->resource,
            ip:         $request->ip(),
            userAgent:  $request->userAgent(),
        );

        $this->auditarConexion($request, $token, $client?->client_name ?? 'Cliente MCP');

        return $this->respuestaDeToken($access, $refresh, $token, $marcado->scopes ?? []);
    }

    /**
     * Deja constancia de la CONEXIÓN de un cliente MCP (la desconexión la registra
     * ApiTokenController). Con ~100 usuarios conectándose solos hace falta saber quién
     * conectó qué y desde dónde; sin esto la única huella sería last_used_at.
     *
     * gp_audit_log.action es un ENUM cerrado ('created','updated','deleted','restored',
     * 'bulk_deleted'), así que se usa 'created' en vez de tocar el esquema.
     */
    private function auditarConexion(Request $request, GpApiToken $token, string $clientName): void
    {
        GpAuditLog::create([
            'user_id'    => $token->user_id,
            'user_name'  => $token->user?->name ?? '',
            'model_type' => 'GpApiToken',
            'model_id'   => $token->id,
            'model_key'  => null,
            'action'     => 'created',
            'old_values' => [],
            'new_values' => [
                'evento'    => 'sesion_mcp_conectada',
                'cliente'   => $clientName,
                'client_id' => $token->client_id,
                'resource'  => $token->resource,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'url'        => substr($request->fullUrl(), 0, 500),
            'method'     => $request->method(),
        ]);
    }

    private function grantRefreshToken(Request $request): JsonResponse
    {
        $plain = (string) $request->input('refresh_token', '');

        if ($plain === '') {
            return $this->errorOauth('invalid_request', 'Falta el parámetro refresh_token.');
        }

        $token = GpApiToken::findFromRefresh($plain);

        // INVARIANTE 5: siempre invalid_grant, nunca un código propio. El cliente
        // decide si rehacer el login mirando exactamente este string.
        if (! $token || $token->isRevoked() || $token->refreshIsExpired()) {
            return $this->errorOauth('invalid_grant', 'Refresh token inválido, revocado o expirado.');
        }

        if ($token->user === null) {
            return $this->errorOauth('invalid_grant', 'El usuario asociado ya no existe.');
        }

        // INVARIANTE 4: rotación. El refresh anterior deja de valer en el acto.
        [$access, $refresh] = $token->rotate();

        return $this->respuestaDeToken($access, $refresh, $token, $token->abilities ?? []);
    }

    private function respuestaDeToken(string $access, string $refresh, GpApiToken $token, array $scopes): JsonResponse
    {
        return response()->json([
            'access_token'  => $access,
            'token_type'    => 'Bearer',
            'expires_in'    => GpApiToken::ACCESS_TTL_MINUTES * 60,
            'refresh_token' => $refresh,
            'scope'         => implode(' ', $scopes ?: [config('gestion-proyectos.oauth.scope', 'mcp')]),
        ])->header('Cache-Control', 'no-store');
    }

    private function errorOauth(string $code, string $descripcion, int $status = 400): JsonResponse
    {
        return response()->json([
            'error'             => $code,
            'error_description' => $descripcion,
        ], $status)->header('Cache-Control', 'no-store');
    }
}
