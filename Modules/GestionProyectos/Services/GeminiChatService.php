<?php

namespace Modules\GestionProyectos\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;
use RuntimeException;

/**
 * Asistente conversacional basado en IA (Google Gemini) — Capítulo 3 de la tesis.
 *
 * Arma el contexto con las tareas asignadas al usuario (para que pueda preguntar
 * cosas como "¿qué tareas tengo pendientes?") y llama a la API pública de Gemini
 * (Generative Language API) vía REST, sin dependencias adicionales.
 */
class GeminiChatService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gemini-2.0-flash',
    ) {
    }

    public static function make(): self
    {
        return new self(
            config('services.gemini.api_key'),
            config('services.gemini.model', 'gemini-2.0-flash'),
        );
    }

    /**
     * Envía el mensaje del usuario a Gemini junto con el contexto de sus tareas
     * asignadas y devuelve el texto de la respuesta.
     */
    public function preguntar(User $usuario, string $mensaje): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'GEMINI_API_KEY no está configurada. Agrégala en tu archivo .env (ver config/services.php).'
            );
        }

        $contexto = $this->construirContextoTareas($usuario);

        $systemInstruction = <<<TEXT
        Eres el asistente conversacional integrado en Zync, una plataforma de gestión de
        proyectos de software. Ayudas al usuario a entender y organizar su trabajo.
        Responde siempre en español, de forma breve y concreta.

        Este es el contexto de las tareas actualmente asignadas al usuario que te habla
        (puede estar vacío si no tiene tareas asignadas):

        {$contexto}

        Si el usuario pregunta por sus tareas, básate en este contexto. Si pregunta algo
        que no tiene relación con sus tareas o con gestión de proyectos, igual puedes
        ayudarlo con conocimiento general (por ejemplo dudas de programación).
        TEXT;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $mensaje]],
                ],
            ],
        ];

        $url = sprintf(self::ENDPOINT, $this->model);

        $response = Http::timeout(30)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('GeminiChatService: fallo al llamar a la API de Gemini', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'No se pudo obtener respuesta del asistente de IA en este momento.'
            );
        }

        $texto = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (!$texto) {
            throw new RuntimeException('El asistente de IA no devolvió una respuesta válida.');
        }

        return trim($texto);
    }

    /**
     * Arma un resumen en texto plano de las tareas asignadas al usuario
     * (no terminadas), para dárselo a Gemini como contexto del prompt.
     */
    private function construirContextoTareas(User $usuario): string
    {
        $estadosFinales = config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']);

        $tareas = Proyecto::query()
            ->where('assignee_id', $usuario->id)
            ->whereNotIn('status', $estadosFinales)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['key', 'summary', 'status', 'priority', 'fecha_limite', 'project']);

        if ($tareas->isEmpty()) {
            return 'El usuario no tiene tareas pendientes asignadas actualmente.';
        }

        return $tareas
            ->map(function (Proyecto $tarea) {
                $fecha = $tarea->fecha_limite ? " (vence: {$tarea->fecha_limite})" : '';
                return "- [{$tarea->key}] {$tarea->summary} — estado: {$tarea->status}, prioridad: {$tarea->priority}{$fecha}";
            })
            ->implode("\n");
    }
}
