<?php

namespace Modules\GestionProyectos\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Modules\GestionProyectos\Models\GpApiToken;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Tests\GestionProyectosTestCase;
use Modules\User\Models\User;

/**
 * Authorization server OAuth 2.1 propio del servidor MCP (Fases 2 y 3 del plan).
 *
 * Es un servidor OAuth escrito a mano —sin Passport— porque el helper del paquete
 * lo hardcodea y encima viene incompleto (ver docs/PLAN-OAUTH-MCP.md). Escribir un
 * authorization server propio es viable, pero cada invariante que se relaje es un
 * fallo de seguridad conocido y publicado: redirección abierta, replay del código,
 * PKCE decorativo, refresh eterno.
 *
 * Por eso estos tests NO cubren "que el flujo funcione" (eso es un solo test), sino
 * sobre todo que el flujo FALLE de la forma correcta. Cada test de invariante fija
 * un ataque concreto, y el de compatibilidad protege lo que ya está en producción:
 * el chatbot de WhatsApp y la API REST siguen entrando con token manual.
 *
 * Los números de INVARIANTE se corresponden con los del plan y con los comentarios
 * marcados en OauthController y en los modelos.
 */
class OauthFlowTest extends GestionProyectosTestCase
{
    /** Callback real de Claude web/Desktop/móvil: es el caso que hay que soportar sí o sí. */
    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    /** URL canónica del MCP. Sin barra final: el "resource" se compara carácter por carácter. */
    private const MCP = '/mcp/gestion-proyectos';

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades del flujo (PKCE, registro, autorización, canje, llamada al MCP)
    // ─────────────────────────────────────────────────────────────────────────

    /** code_verifier aleatorio (RFC 7636: 43-128 caracteres del alfabeto no reservado). */
    private function verifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    /** code_challenge S256 = base64url(sha256(verifier)) — exactamente lo que verifica el server. */
    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Registra un cliente por DCR y devuelve su client_id. */
    private function registrarCliente(array $redirectUris = [self::REDIRECT]): string
    {
        $r = $this->postJson('/oauth/register', [
            'client_name'   => 'Claude',
            'redirect_uris' => $redirectUris,
        ]);

        $r->assertStatus(201);

        return $r->json('client_id');
    }

    /**
     * Aprueba la pantalla de consentimiento y devuelve la respuesta (un redirect al
     * cliente). Requiere sesión web: es una persona delante del navegador.
     */
    private function aprobar(string $clientId, string $challenge, array $extra = []): TestResponse
    {
        return $this->post('/oauth/authorize', array_merge([
            'client_id'             => $clientId,
            'redirect_uri'          => self::REDIRECT,
            'response_type'         => 'code',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'scope'                 => 'mcp offline_access',
            'state'                 => 'estado-de-prueba',
            'accion'                => 'aprobar',
        ], $extra));
    }

    /** Extrae un parámetro del querystring del redirect al cliente. */
    private function paramDelRedirect(TestResponse $r, string $param): ?string
    {
        parse_str((string) parse_url((string) $r->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query[$param] ?? null;
    }

    /** POST /oauth/token — form-urlencoded, como manda el RFC (no JSON). */
    private function canjear(array $params): TestResponse
    {
        return $this->post('/oauth/token', $params);
    }

    /**
     * Llama al MCP con (o sin) Bearer. Solo interesa el STATUS: si no es 401, el token
     * autenticó. El cuerpo JSON-RPC es asunto del paquete laravel/mcp, no de OAuth.
     */
    private function mcp(?string $accessToken): TestResponse
    {
        $headers = $accessToken ? ['Authorization' => 'Bearer ' . $accessToken] : [];

        return $this->postJson(self::MCP, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'tools/list',
        ], $headers);
    }

    /** Flujo entero de una tirada: devuelve el JSON de /oauth/token. */
    private function flujoCompleto(User $user): array
    {
        $verifier = $this->verifier();
        $clientId = $this->registrarCliente();

        $code = $this->paramDelRedirect(
            $this->actingAs($user)->aprobar($clientId, $this->challenge($verifier)),
            'code'
        );

        return $this->canjear([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'redirect_uri'  => self::REDIRECT,
            'code_verifier' => $verifier,
        ])->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Descubrimiento
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * RFC 8414. Los clientes MCP leen este documento ANTES de arrancar y abortan si no
     * encuentran S256: sin PKCE no hay flujo para un cliente público. Y sin
     * refresh_token en grant_types_supported ni siquiera pedirían sesión larga, con lo
     * que el usuario tendría que relogearse cada hora.
     */
    public function test_metadata_del_authorization_server_anuncia_pkce_s256_y_refresh(): void
    {
        $r = $this->getJson('/.well-known/oauth-authorization-server');

        $r->assertOk();

        $this->assertSame(['S256'], $r->json('code_challenge_methods_supported'));

        $this->assertContains('authorization_code', $r->json('grant_types_supported'));
        $this->assertContains('refresh_token', $r->json('grant_types_supported'));

        $this->assertNotEmpty($r->json('issuer'));
        $this->assertNotEmpty($r->json('authorization_endpoint'));
        $this->assertNotEmpty($r->json('token_endpoint'));
    }

    /**
     * RFC 9728. Es el documento al que apunta el WWW-Authenticate del 401 y el que
     * arranca todo: dice QUÉ recurso es (audiencia) y QUIÉN lo autoriza. Si
     * authorization_servers viniera vacío, el cliente no sabría a dónde ir a loguear.
     */
    public function test_metadata_del_recurso_protegido_expone_resource_y_authorization_servers(): void
    {
        $r = $this->getJson('/.well-known/oauth-protected-resource');

        $r->assertOk();

        $this->assertNotEmpty($r->json('resource'));
        $this->assertNotEmpty($r->json('authorization_servers'));
        $this->assertIsArray($r->json('authorization_servers'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Registro dinámico de clientes (DCR, RFC 7591)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Claude registra un cliente nuevo en cada conexión fresca. Si /oauth/register no
     * devuelve 201 con client_id, el usuario no llega ni a la pantalla de login.
     */
    public function test_registro_dinamico_devuelve_client_id(): void
    {
        $r = $this->postJson('/oauth/register', [
            'client_name'   => 'Claude',
            'redirect_uris' => [self::REDIRECT],
        ]);

        $r->assertStatus(201);
        $this->assertNotEmpty($r->json('client_id'));
        $this->assertSame([self::REDIRECT], $r->json('redirect_uris'));
    }

    /**
     * OAuth 2.1 §1.5: solo https o loopback. El registro es PÚBLICO (cualquiera puede
     * llamarlo), así que aceptar http hacia un host externo convertiría el authorization
     * server en un canal para exfiltrar códigos por la red en claro.
     */
    public function test_registro_rechaza_redirect_uri_http_que_no_es_loopback(): void
    {
        $r = $this->postJson('/oauth/register', [
            'client_name'   => 'Cliente malicioso',
            'redirect_uris' => ['http://atacante.example.com/callback'],
        ]);

        $r->assertStatus(400);
    }

    /**
     * La otra cara: Claude Code usa loopback con puerto efímero (RFC 8252), así que
     * http://localhost y http://127.0.0.1 SÍ tienen que poder registrarse. Si esto se
     * cerrara de más, se rompería el cliente de escritorio.
     */
    public function test_registro_acepta_loopback_http(): void
    {
        $this->postJson('/oauth/register', [
            'client_name'   => 'Claude Code',
            'redirect_uris' => ['http://localhost/callback', 'http://127.0.0.1/callback'],
        ])->assertStatus(201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. El flujo completo (registro → consentimiento → código → token → MCP)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * El camino feliz de punta a punta, que es lo que hará el usuario real: pega una URL,
     * se loguea con su cuenta de siempre y aprueba.
     *
     * La comprobación que de verdad importa es la última: el access_token emitido AUTENTICA
     * contra el MCP. Sin eso, el flujo podría devolver un token con pinta correcta que el
     * middleware no reconoce, y el error solo aparecería en producción con 100 usuarios.
     */
    public function test_flujo_completo_entrega_un_access_token_que_autentica_en_el_mcp(): void
    {
        $user     = $this->usuario();
        $verifier = $this->verifier();
        $clientId = $this->registrarCliente();

        $redirect = $this->actingAs($user)->aprobar($clientId, $this->challenge($verifier));

        $redirect->assertRedirect();
        $this->assertStringStartsWith(self::REDIRECT, (string) $redirect->headers->get('Location'));

        // El state vuelve tal cual: es la defensa CSRF del propio cliente.
        $this->assertSame('estado-de-prueba', $this->paramDelRedirect($redirect, 'state'));

        $code = $this->paramDelRedirect($redirect, 'code');
        $this->assertNotEmpty($code, 'El redirect de aprobación debe traer un code.');

        $token = $this->canjear([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'redirect_uri'  => self::REDIRECT,
            'code_verifier' => $verifier,
        ]);

        $token->assertOk();
        $this->assertSame('Bearer', $token->json('token_type'));
        $this->assertNotEmpty($token->json('access_token'));
        $this->assertNotEmpty($token->json('refresh_token'));
        $this->assertSame(GpApiToken::ACCESS_TTL_MINUTES * 60, $token->json('expires_in'));

        // La prueba de fuego: el token sirve de verdad contra el servidor MCP.
        $this->assertNotSame(
            401,
            $this->mcp($token->json('access_token'))->status(),
            'El access_token emitido por OAuth debe autenticar contra el MCP.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Invariantes de seguridad — cada uno debe fallar de la forma correcta
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * INVARIANTE 1 — comparación exacta de redirect_uri.
     *
     * Si el server redirigiera a una URI no registrada, sería una redirección abierta con
     * el código de autorización dentro: el atacante solo tendría que convencer a la víctima
     * de pulsar un enlace. Por eso este error NO se redirige (no hay destino en el que
     * confiar, OAuth 2.1 §7.12): se muestra en pantalla.
     */
    public function test_invariante_1_redirect_uri_no_registrada_no_redirige(): void
    {
        $clientId = $this->registrarCliente();

        $r = $this->actingAs($this->usuario())->get('/oauth/authorize?' . http_build_query([
            'client_id'             => $clientId,
            'redirect_uri'          => 'https://atacante.example.com/robar',
            'response_type'         => 'code',
            'code_challenge'        => $this->challenge($this->verifier()),
            'code_challenge_method' => 'S256',
            'state'                 => 'x',
        ]));

        $r->assertStatus(400);
        $r->assertHeaderMissing('Location');
    }

    /**
     * INVARIANTE 2 — el código es de un solo uso.
     *
     * El código viaja por la barra de direcciones y queda en historial, logs de proxy y
     * Referer. Si fuera reutilizable, cualquiera que lo recupere obtendría un token. El
     * used_at se marca dentro de la misma transacción que lo canjea, así que ni siquiera
     * dos canjes simultáneos pueden colarse.
     */
    public function test_invariante_2_el_codigo_solo_se_puede_canjear_una_vez(): void
    {
        $user     = $this->usuario();
        $verifier = $this->verifier();
        $clientId = $this->registrarCliente();

        $code = $this->paramDelRedirect(
            $this->actingAs($user)->aprobar($clientId, $this->challenge($verifier)),
            'code'
        );

        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'redirect_uri'  => self::REDIRECT,
            'code_verifier' => $verifier,
        ];

        $this->canjear($params)->assertOk();

        $segundo = $this->canjear($params);

        $segundo->assertStatus(400);
        $this->assertSame('invalid_grant', $segundo->json('error'));
    }

    /**
     * INVARIANTE 3 — PKCE verificado de verdad.
     *
     * PKCE existe para que un código robado no sirva sin el verifier original. Si el server
     * aceptara cualquier verifier (o no lo comparase), PKCE sería decorativo y el robo del
     * código volvería a ser suficiente para obtener un token.
     */
    public function test_invariante_3_un_code_verifier_equivocado_no_canjea(): void
    {
        $user     = $this->usuario();
        $verifier = $this->verifier();
        $clientId = $this->registrarCliente();

        $code = $this->paramDelRedirect(
            $this->actingAs($user)->aprobar($clientId, $this->challenge($verifier)),
            'code'
        );

        $r = $this->canjear([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'redirect_uri'  => self::REDIRECT,
            'code_verifier' => $this->verifier(), // otro verifier, challenge que no cuadra
        ]);

        $r->assertStatus(400);
        $this->assertSame('invalid_grant', $r->json('error'));
    }

    /**
     * INVARIANTE 3 (segunda mitad) — PKCE es OBLIGATORIO, no opcional.
     *
     * De nada sirve verificar bien el challenge si se puede omitir: el atacante
     * simplemente no lo enviaría. Sin code_challenge el flujo ni arranca.
     */
    public function test_invariante_3_authorize_sin_code_challenge_no_arranca(): void
    {
        $clientId = $this->registrarCliente();

        $r = $this->actingAs($this->usuario())->get('/oauth/authorize?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => self::REDIRECT,
            'response_type' => 'code',
            'state'         => 'x',
        ]));

        $r->assertStatus(400);
    }

    /**
     * INVARIANTE 4 — rotación del refresh token.
     *
     * Obligatoria para clientes públicos: el refresh dura 30 días y no está protegido por
     * secreto de cliente. Rotando, un refresh robado deja de servir en cuanto el cliente
     * legítimo lo use una vez, y el uso del viejo delata el robo. Si no rotara, quien lo
     * copie tendría un mes de acceso silencioso.
     */
    public function test_invariante_4_el_refresh_rota_y_el_anterior_deja_de_servir(): void
    {
        $inicial = $this->flujoCompleto($this->usuario());
        $viejo   = $inicial['refresh_token'];

        $r = $this->canjear(['grant_type' => 'refresh_token', 'refresh_token' => $viejo]);

        $r->assertOk();
        $nuevo = $r->json('refresh_token');

        $this->assertNotEmpty($nuevo);
        $this->assertNotSame($viejo, $nuevo, 'El refresh token debe rotar en cada uso.');
        $this->assertNotEmpty($r->json('access_token'));

        // Y el anterior queda muerto en el acto, en la misma operación que emitió el nuevo.
        $reintento = $this->canjear(['grant_type' => 'refresh_token', 'refresh_token' => $viejo]);

        $reintento->assertStatus(400);
        $this->assertSame('invalid_grant', $reintento->json('error'));

        // El nuevo sí funciona: la rotación no rompe la sesión del cliente honesto.
        $this->canjear(['grant_type' => 'refresh_token', 'refresh_token' => $nuevo])->assertOk();
    }

    /**
     * INVARIANTE 5 — errores RFC 6749 literales.
     *
     * El cliente decide si vuelve a mandar al usuario a loguear mirando EXACTAMENTE el
     * string 'invalid_grant'. Con 'invalid_request', un código propio o un 500, Claude no
     * relogea: se queda en un bucle de fallos y el usuario ve una integración rota sin
     * explicación.
     */
    public function test_invariante_5_un_refresh_invalido_devuelve_exactamente_invalid_grant(): void
    {
        $r = $this->canjear([
            'grant_type'    => 'refresh_token',
            'refresh_token' => '999999|refresh-que-no-existe',
        ]);

        $r->assertStatus(400);
        $this->assertSame('invalid_grant', $r->json('error'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Vida del access token contra el MCP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * El access token dura 1 hora. Todo el sentido de acortarlo es que un token filtrado
     * caduque solo; si el middleware no mirase expires_at, la caducidad sería un adorno en
     * la base de datos y el token robado valdría para siempre.
     */
    public function test_access_token_expirado_ya_no_autentica_en_el_mcp(): void
    {
        $token  = $this->flujoCompleto($this->usuario())['access_token'];
        $modelo = GpApiToken::findFromPlain($token);

        $this->assertNotNull($modelo);
        $this->assertNotSame(401, $this->mcp($token)->status(), 'Recién emitido debe valer.');

        $modelo->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->mcp($token)->assertStatus(401);
    }

    /**
     * "Desconectar" desde el panel de Sesiones MCP tiene que cortar de verdad y al
     * instante. Si el middleware ignorase revoked_at, el usuario creería haber cortado el
     * acceso sin haberlo cortado — que es justo el caso en el que uno revoca: cuando cree
     * que le robaron el equipo.
     */
    public function test_token_revocado_ya_no_autentica_en_el_mcp(): void
    {
        $token  = $this->flujoCompleto($this->usuario())['access_token'];
        $modelo = GpApiToken::findFromPlain($token);

        $this->assertNotNull($modelo);
        $this->assertNotSame(401, $this->mcp($token)->status(), 'Antes de revocar debe valer.');

        $modelo->revoke();

        $this->mcp($token)->assertStatus(401);
    }

    /**
     * El 401 no es solo un "no": es el disparador de TODO el flujo OAuth. El cliente MCP
     * llega sin credencial, recibe este 401 y aprende de la cabecera dónde está el metadata
     * del recurso. Sin WWW-Authenticate con resource_metadata, Claude concluye que aquí no
     * hay OAuth y le pide al usuario un token manual — exactamente lo que se quiere quitar.
     */
    public function test_el_401_del_mcp_sin_token_apunta_al_metadata_del_recurso(): void
    {
        $r = $this->mcp(null);

        $r->assertStatus(401);

        $cabecera = (string) $r->headers->get('WWW-Authenticate');

        $this->assertNotEmpty($cabecera, 'El 401 debe traer WWW-Authenticate.');
        $this->assertStringContainsString('Bearer', $cabecera);
        $this->assertStringContainsString('resource_metadata', $cabecera);
        $this->assertStringContainsString('.well-known/oauth-protected-resource', $cabecera);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Compatibilidad — no romper lo que ya está en producción
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * REGRESIÓN CRÍTICA. Los tokens manuales (client_id NULL, sin expiración) son la vía
     * oficial de las integraciones servidor a servidor, que no pueden hacer OAuth porque el
     * flujo exige un navegador y una persona aprobando. Hoy los usan el CHATBOT DE WHATSAPP
     * contra este mismo MCP y la API REST, que comparten middleware y tabla.
     *
     * Endurecer el middleware con expires_at, revoked_at y audiencia es fácil que se lleve
     * por delante a estos tokens: expires_at NULL no es "caducado" y resource NULL no es
     * "audiencia ajena". Este test es el que avisa antes de tirar el chatbot.
     */
    public function test_un_token_manual_sin_caducidad_sigue_autenticando_en_el_mcp(): void
    {
        [$plain, $modelo] = GpApiToken::generate($this->usuario()->id, 'Chatbot WhatsApp');

        // Un token manual es exactamente eso: sin cliente OAuth, sin caducidad, sin audiencia.
        $this->assertNull($modelo->client_id);
        $this->assertNull($modelo->expires_at);
        $this->assertNull($modelo->resource);

        $this->assertNotSame(
            401,
            $this->mcp($plain)->status(),
            'Los tokens manuales deben seguir entrando: sostienen el chatbot y la API REST.'
        );
    }

    /**
     * El plan exige registrar en gp_audit_log tanto la conexión como la desconexión.
     * Con ~100 usuarios conectándose por su cuenta, sin esta huella lo único que
     * quedaría sería last_used_at, que no dice quién conectó qué ni desde dónde.
     */
    public function test_la_conexion_queda_registrada_en_la_auditoria(): void
    {
        $user = $this->usuario();

        $this->flujoCompleto($user);

        $entrada = GpAuditLog::where('model_type', 'GpApiToken')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entrada, 'La conexión debe dejar rastro en gp_audit_log.');
        $this->assertSame('sesion_mcp_conectada', $entrada->new_values['evento'] ?? null);
        $this->assertSame('Claude', $entrada->new_values['cliente'] ?? null);
        $this->assertNotEmpty($entrada->new_values['client_id'] ?? null);
    }
}
