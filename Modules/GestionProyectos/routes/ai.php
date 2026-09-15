<?php

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Facades\Mcp;
use Modules\GestionProyectos\Http\Controllers\OauthController;
use Modules\GestionProyectos\Http\Middleware\AuthGpApiToken;
use Modules\GestionProyectos\Http\Middleware\ForceContentLength;
use Modules\GestionProyectos\Mcp\GestionProyectosServer;

/*
|--------------------------------------------------------------------------
| GestionProyectos — MCP (Model Context Protocol) sobre HTTP
|--------------------------------------------------------------------------
| Expone el CRUD SCRUM como servidor MCP remoto en POST /mcp/gestion-proyectos.
| Autenticación por token Bearer (AuthGpApiToken): respeta roles, espacios y reglas
| de estado. Los tokens salen de OAuth (usuarios) o de la generación manual reservada
| a administradores (integraciones servidor-a-servidor, como el chatbot de WhatsApp).
|
| ForceContentLength: el server de dev de PHP responde sin Content-Length y con
| Connection: close → crashea al cliente undici de mcp-remote. El middleware lo fija.
*/

Route::middleware([ForceContentLength::class])->group(function () {

    // throttle:120,1 → 120 peticiones/minuto por token autenticado.
    // AuthGpApiToken corre antes del throttle para que la clave sea el user_id, no la IP.
    Mcp::web('mcp/gestion-proyectos', GestionProyectosServer::class)
        ->middleware(['api', AuthGpApiToken::class, 'throttle:120,1']);
});

/*
|--------------------------------------------------------------------------
| OAuth 2.1 — authorization server propio
|--------------------------------------------------------------------------
| Implementado a mano, sin Passport ni dependencias nuevas: el helper
| Mcp::oauthRoutes() de laravel/mcp hardcodea Passport y encima solo publica los
| .well-known y /register — no trae /authorize ni /token.
|
| Antes aquí había stubs que devolvían 404 en los .well-known para que los clientes
| concluyeran "sin OAuth" y usaran el Bearer manual. Ya no: ahora publican metadata
| real y son el arranque del flujo. Ver docs/PLAN-OAUTH-MCP.md.
*/

// ── Descubrimiento (público, sin auth: así lo exige el protocolo) ────────────
// Se registran ambas formas del protected-resource metadata: la variante con el path
// del MCP es la que los clientes sondean primero.
Route::get('/.well-known/oauth-protected-resource/mcp/gestion-proyectos', [OauthController::class, 'protectedResource']);
Route::get('/.well-known/oauth-protected-resource', [OauthController::class, 'protectedResource']);
Route::get('/.well-known/oauth-authorization-server', [OauthController::class, 'authorizationServer']);

// ── Registro dinámico de clientes (RFC 7591). Body JSON. ────────────────────
Route::post('oauth/register', [OauthController::class, 'register']);

// ── Autorización: exige sesión web, así que 'auth' redirige al login existente ──
// Con el login de Breeze que ya tiene el sistema; al volver, Laravel restaura la
// URL pretendida y el usuario cae en la pantalla de consentimiento.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('oauth/authorize', [OauthController::class, 'authorize'])->name('gp-oauth.authorize');
    Route::post('oauth/authorize', [OauthController::class, 'approve'])->name('gp-oauth.approve');
});

// ── Emisión de tokens. Body form-urlencoded (RFC 6749 §4.1.3), sin sesión. ──
Route::post('oauth/token', [OauthController::class, 'token'])->middleware('api');
