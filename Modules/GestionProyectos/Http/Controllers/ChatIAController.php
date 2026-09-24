<?php

namespace Modules\GestionProyectos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\GestionProyectos\Models\ChatIA;
use Modules\GestionProyectos\Services\GeminiChatService;
use Throwable;

/**
 * Asistente conversacional con IA (Google Gemini) — disponible para
 * cualquier usuario autenticado, sin distinción de rol.
 */
class ChatIAController extends Controller
{
    /**
     * Devuelve el historial de conversación del usuario autenticado
     * (últimos 50 turnos, orden cronológico).
     */
    public function index(): JsonResponse
    {
        $historial = ChatIA::query()
            ->where('id_usuario', Auth::id())
            ->orderBy('fecha_creacion')
            ->limit(50)
            ->get(['id', 'mensaje', 'respuesta', 'fecha_creacion', 'tipo']);

        return response()->json(['historial' => $historial]);
    }

    /**
     * Recibe un mensaje del usuario, lo envía a Gemini junto con el
     * contexto de sus tareas, y guarda el turno completo (pregunta + respuesta).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:4000'],
        ]);

        $usuario = Auth::user();
        $gemini = GeminiChatService::make();

        try {
            $respuesta = $gemini->preguntar($usuario, $data['mensaje']);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        $turno = ChatIA::create([
            'id_usuario' => $usuario->id,
            'mensaje' => $data['mensaje'],
            'respuesta' => $respuesta,
            'fecha_creacion' => now(),
            'tipo' => 'consulta',
        ]);

        return response()->json(['turno' => $turno]);
    }
}
