<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpApiToken;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpOauthConsent;

/**
 * Sesiones MCP del usuario autenticado (vía sesión web).
 *
 * Al estilo de los dispositivos vinculados de WhatsApp: cada quien ve y corta SOLO
 * lo suyo, por eso todas las consultas van filtradas por user_id sin excepción.
 *
 * La misma tabla guarda las sesiones OAuth y los tokens manuales de integración
 * (chatbot de WhatsApp, API REST); los distingue client_id → isOauthSession().
 */
class ApiTokenController extends Controller
{
    /**
     * Días que una sesión muerta se sigue mostrando como "expirada".
     * Que desaparezca sin explicación confunde más que verla apagada.
     */
    private const DIAS_GRACIA = 7;

    // GET /gestion-proyectos/api-tokens
    public function index(Request $request): JsonResponse
    {
        $sesiones = GpApiToken::where('user_id', $request->user()->id)
            ->orderByRaw('last_used_at IS NULL') // las que nunca se usaron, al final
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (GpApiToken $token) {
                $estado = $this->estadoDe($token);

                // null = lleva más de DIAS_GRACIA apagada: no se muestra, la borra gp:oauth-gc.
                return $estado === null ? null : [
                    'id'           => $token->id,
                    'name'         => $token->name,
                    'es_oauth'     => $token->isOauthSession(),
                    'ip_address'   => $token->ip_address,
                    'user_agent'   => $token->user_agent,
                    'last_used_at' => $token->last_used_at,
                    'created_at'   => $token->created_at,
                    'expires_at'   => $token->expires_at,
                    'estado'       => $estado,
                ];
            })
            ->filter()
            ->values();

        return response()->json($sesiones);
    }

    /**
     * Estado de cara al panel: 'activa', 'expirada' o null (ya no se lista).
     *
     * El momento en que la sesión dejó de servir es el PRIMERO de los dos que hayan
     * ocurrido: una revocación temprana mata también un refresh que aún no vencía, y
     * un refresh vencido la mata aunque nadie la haya revocado.
     *
     * El access token caducado NO cuenta: dura 1 hora y el refresh lo renueva solo.
     */
    private function estadoDe(GpApiToken $token): ?string
    {
        if (!$token->isRevoked() && !$token->refreshIsExpired()) {
            return 'activa';
        }

        $momentos = array_filter([
            $token->revoked_at,
            $token->refreshIsExpired() ? $token->refresh_expires_at : null,
        ]);

        $fin = $momentos ? min($momentos) : null;

        return $fin === null || $fin->gte(now()->subDays(self::DIAS_GRACIA))
            ? 'expirada'
            : null;
    }

    // POST /gestion-proyectos/api-tokens
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ya no es una función pública. La generación manual sobrevive como vía oficial
        // de las integraciones servidor a servidor (hoy el chatbot de WhatsApp), que no
        // pueden hacer OAuth porque el flujo exige un navegador y una persona aprobando.
        // Un usuario normal conecta su cliente MCP por OAuth, no pegando tokens.
        if (!$user->hasRole('admin') && !$user->can('gestion-proyectos.admin')) {
            return response()->json([
                'message' => 'Solo un administrador puede generar tokens de integración. Para conectar tu cliente MCP usa el flujo de Sesiones MCP.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        [$plainToken, $model] = GpApiToken::generate(
            $user->id,
            $validated['name'] ?? 'API Token'
        );

        // El texto plano se devuelve UNA sola vez; después no es recuperable.
        return response()->json([
            'token'      => $plainToken,
            'id'         => $model->id,
            'name'       => $model->name,
            'created_at' => $model->created_at,
        ], 201);
    }

    // DELETE /gestion-proyectos/api-tokens/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $token = GpApiToken::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$token) {
            return response()->json(['message' => 'Sesión no encontrada.'], 404);
        }

        // Revocar, olvidar el consentimiento y auditar van SIEMPRE juntos, en la misma
        // transacción: si solo se revoca el token, el cliente rehace el flujo OAuth, se
        // encuentra el consentimiento recordado y se reconecta solo sin preguntar nada
        // — el usuario creería haber cortado sin haber cortado.
        DB::transaction(function () use ($request, $token, $user) {
            $token->revoke();
            GpOauthConsent::olvidar($user->id, $token->name);
            $this->auditar($request, 'deleted', $token->id, [
                'sesion_id' => $token->id,
                'cliente'   => $token->name,
                'es_oauth'  => $token->isOauthSession(),
            ]);
        });

        return response()->json(['message' => 'Sesión desconectada.']);
    }

    // DELETE /gestion-proyectos/api-tokens
    public function destroyAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Solo sesiones OAuth y solo las vivas:
        // - whereNotNull('client_id') deja fuera los tokens manuales. "Desconectar todas"
        //   vive en un panel que se lee como "mis dispositivos conectados"; un token manual
        //   es una integración servidor a servidor (hoy el chatbot de WhatsApp) y tumbarla
        //   desde aquí sería un efecto colateral invisible y difícil de diagnosticar. Cada
        //   token manual se sigue pudiendo cortar con destroy(), que es un acto deliberado.
        // - whereNull('revoked_at') evita revocar de nuevo una sesión ya cortada: le movería
        //   el revoked_at y la resucitaría en el listado otros 7 días.
        $sesiones = GpApiToken::where('user_id', $userId)
            ->whereNotNull('client_id')
            ->whereNull('revoked_at')
            ->get();

        // Sin nada que cortar no se abre transacción ni se ensucia la auditoría.
        if ($sesiones->isEmpty()) {
            return response()->json(['message' => 'No había sesiones activas.', 'cerradas' => 0]);
        }

        // Mismo motivo que en destroy(): sin borrar el consentimiento la desconexión es
        // aparente. Los nombres se deduplican porque el consentimiento se guarda por
        // usuario + nombre de cliente, no por sesión.
        DB::transaction(function () use ($request, $sesiones, $userId) {
            foreach ($sesiones as $token) {
                $token->revoke();
            }

            foreach ($sesiones->pluck('name')->unique() as $cliente) {
                GpOauthConsent::olvidar($userId, $cliente);
            }

            $this->auditar($request, 'bulk_deleted', null, [
                'total'        => $sesiones->count(),
                'sesiones_ids' => $sesiones->pluck('id')->all(),
                'clientes'     => $sesiones->pluck('name')->unique()->values()->all(),
            ]);
        });

        return response()->json([
            'message'  => 'Sesiones desconectadas.',
            'cerradas' => $sesiones->count(),
        ]);
    }

    /**
     * Deja constancia del corte en gp_audit_log (el plan lo exige para cada conexión y
     * desconexión OAuth). Se llama SIEMPRE dentro de la transacción del corte: una
     * desconexión sin rastro y un rastro sin desconexión son igual de inútiles.
     *
     * gp_audit_log.action es un ENUM cerrado ('created','updated','deleted','restored',
     * 'bulk_deleted'), así que se reutilizan sus valores en lugar de tocar el esquema:
     * 'deleted' para el corte individual y 'bulk_deleted' para el masivo.
     */
    private function auditar(Request $request, string $action, ?int $modelId, array $detalle): void
    {
        $user = $request->user();

        GpAuditLog::create([
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'model_type' => 'GpApiToken',
            'model_id'   => $modelId,
            'model_key'  => null,
            'action'     => $action,
            'old_values' => $detalle,
            'new_values' => [],
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'url'        => substr($request->fullUrl(), 0, 500),
            'method'     => $request->method(),
        ]);
    }
}
