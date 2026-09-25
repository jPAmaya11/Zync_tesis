<?php

namespace Modules\GestionProyectos\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\User\Models\User;
use RuntimeException;

/**
 * Asistente conversacional basado en IA (Google Gemini) — Capítulo 3 de la tesis.
 *
 * Arma el contexto con las tareas y proyectos del usuario y llama a la API pública
 * de Gemini (Generative Language API) vía REST. Además de responder preguntas,
 * puede EJECUTAR acciones reales sobre la plataforma (crear tarea, cambiar estado,
 * eliminar tarea, registrar bug) usando "function calling" de Gemini: el modelo
 * decide qué función llamar y con qué datos, y este servicio ejecuta la acción
 * reutilizando exactamente la misma lógica de negocio/permisos que ya usan el
 * controller web y el servidor MCP (ProyectoServiceInterface) — el chat NO
 * duplica reglas de permisos, solo delega en el mismo servicio y respeta
 * cualquier excepción de autorización que este lance.
 */
class GeminiChatService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gemini-3.8-flash',
    ) {
    }

    public static function make(): self
    {
        return new self(
            config('services.gemini.api_key'),
            config('services.gemini.model', 'gemini-3.8-flash'),
        );
    }

    /**
     * Envía el mensaje del usuario a Gemini junto con el contexto de sus tareas y
     * proyectos, dejando que el modelo decida si debe ejecutar alguna acción sobre
     * la plataforma (crear tarea, cambiar estado, eliminar, registrar bug) o si
     * solo debe responder en texto.
     *
     * @return array{texto: string, accion: ?string} 'accion' es el nombre de la
     *         función ejecutada (crear_tarea, actualizar_estado_tarea,
     *         eliminar_tarea, registrar_bug) o null si solo fue una consulta.
     */
    public function preguntar(User $usuario, string $mensaje): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'GEMINI_API_KEY no está configurada. Agrégala en tu archivo .env (ver config/services.php).'
            );
        }

        $systemInstruction = $this->construirSystemInstruction($usuario);

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $mensaje]]],
        ];

        $primera = $this->llamarGemini($systemInstruction, $contents);

        $parte = data_get($primera, 'candidates.0.content.parts.0');
        $functionCall = data_get($parte, 'functionCall');

        if (!$functionCall) {
            $texto = data_get($primera, 'candidates.0.content.parts.0.text');
            if (!$texto) {
                throw new RuntimeException('El asistente de IA no devolvió una respuesta válida.');
            }

            return ['texto' => trim($texto), 'accion' => null];
        }

        // El modelo pidió ejecutar una acción: la ejecutamos localmente (reutilizando
        // el mismo servicio/permisos que la web y el MCP) y le devolvemos el
        // resultado para que redacte la confirmación final en lenguaje natural.
        $nombreFuncion = (string) data_get($functionCall, 'name');
        $argumentos = (array) data_get($functionCall, 'args', []);

        $resultado = $this->ejecutarAccion($usuario, $nombreFuncion, $argumentos);

        $contents[] = [
            'role' => 'model',
            'parts' => [['functionCall' => ['name' => $nombreFuncion, 'args' => $argumentos]]],
        ];
        $contents[] = [
            'role' => 'function',
            'parts' => [[
                'functionResponse' => [
                    'name' => $nombreFuncion,
                    'response' => $resultado,
                ],
            ]],
        ];

        $segunda = $this->llamarGemini($systemInstruction, $contents);
        $textoFinal = data_get($segunda, 'candidates.0.content.parts.0.text');

        if (!$textoFinal) {
            // Si el modelo no redactó nada, devolvemos al menos el mensaje del resultado.
            $textoFinal = $resultado['message'] ?? $resultado['error'] ?? 'Listo.';
        }

        return ['texto' => trim($textoFinal), 'accion' => $nombreFuncion];
    }

    /**
     * Llama a la API de Gemini (generateContent) con el catálogo de funciones
     * disponibles y devuelve el JSON decodificado de la respuesta.
     */
    private function llamarGemini(string $systemInstruction, array $contents): array
    {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => $contents,
            'tools' => [
                ['function_declarations' => $this->declararFunciones()],
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

        return (array) $response->json();
    }

    /**
     * Catálogo de acciones que el asistente puede ejecutar sobre la plataforma.
     * Deliberadamente acotado a lo que describe la tesis (Cap. 3): crear tarea,
     * actualizar estado, eliminar tarea y registrar bug. Ninguna otra acción del
     * sistema (usuarios, proyectos, miembros, etc.) es invocable desde el chat.
     */
    private function declararFunciones(): array
    {
        return [
            [
                'name' => 'crear_tarea',
                'description' => 'Crea una tarea nueva dentro de un proyecto/espacio de Zync. Requiere permiso de '
                    . 'creación en ese espacio (Administrador o Jefe de Proyecto). Un Tester solo puede usarla '
                    . 'para registrar bugs (usar la función registrar_bug en ese caso).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_key' => ['type' => 'STRING', 'description' => 'Key del proyecto/espacio, ej. ERP.'],
                        'summary' => ['type' => 'STRING', 'description' => 'Título de la tarea.'],
                        'priority' => ['type' => 'STRING', 'enum' => ['Alta', 'Media', 'Baja']],
                        'assignee_nombre' => ['type' => 'STRING', 'description' => 'Nombre de la persona a asignar (opcional).'],
                        'fecha_limite' => ['type' => 'STRING', 'description' => 'Fecha límite YYYY-MM-DD (opcional).'],
                    ],
                    'required' => ['project_key', 'summary'],
                ],
            ],
            [
                'name' => 'actualizar_estado_tarea',
                'description' => 'Cambia el estado de una tarea existente por su key (ej. ERP-0012). Respeta las '
                    . 'reglas de la plataforma: un Desarrollador/Diseñador/Tester solo puede mover su propia tarea '
                    . 'entre estados no críticos; pasar a Finalizado, Reprogramado o Cancelado requiere ser '
                    . 'Jefe de Proyecto o Administrador.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'tarea_key' => ['type' => 'STRING', 'description' => 'Key de la tarea, ej. ERP-0012.'],
                        'nuevo_estado' => [
                            'type' => 'STRING',
                            'enum' => ['Pendiente', 'En Curso', 'En Revisión', 'En Pausa', 'Finalizado', 'Reprogramado', 'Cancelado'],
                        ],
                    ],
                    'required' => ['tarea_key', 'nuevo_estado'],
                ],
            ],
            [
                'name' => 'eliminar_tarea',
                'description' => 'Elimina (soft delete) una tarea por su key. Solo Administrador o Jefe de Proyecto '
                    . 'del espacio pueden hacerlo. Es una acción destructiva.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'tarea_key' => ['type' => 'STRING', 'description' => 'Key de la tarea a eliminar.'],
                    ],
                    'required' => ['tarea_key'],
                ],
            ],
            [
                'name' => 'registrar_bug',
                'description' => 'Registra un bug/incidencia como una tarea de tipo Error dentro de un proyecto. '
                    . 'Disponible para el rol Tester (y también para Administrador/Jefe de Proyecto).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_key' => ['type' => 'STRING', 'description' => 'Key del proyecto/espacio.'],
                        'summary' => ['type' => 'STRING', 'description' => 'Título breve del bug.'],
                        'descripcion' => ['type' => 'STRING', 'description' => 'Detalle del bug encontrado (opcional).'],
                    ],
                    'required' => ['project_key', 'summary'],
                ],
            ],
        ];
    }

    /**
     * Ejecuta localmente la acción pedida por el modelo, delegando SIEMPRE en
     * ProyectoServiceInterface (el mismo servicio que usan el controller web y
     * el servidor MCP) para que las reglas de permisos por rol se apliquen
     * exactamente igual que en el resto de la plataforma.
     */
    private function ejecutarAccion(User $usuario, string $nombreFuncion, array $args): array
    {
        try {
            return match ($nombreFuncion) {
                'crear_tarea' => $this->crearTarea($usuario, $args, false),
                'registrar_bug' => $this->crearTarea($usuario, $args, true),
                'actualizar_estado_tarea' => $this->actualizarEstado($usuario, $args),
                'eliminar_tarea' => $this->eliminarTarea($usuario, $args),
                default => ['error' => "Acción no reconocida: {$nombreFuncion}."],
            };
        } catch (AuthorizationException $e) {
            return ['error' => 'Sin permiso: ' . $e->getMessage()];
        } catch (GestionProyectosException $e) {
            return ['error' => $e->getMessage()];
        } catch (ModelNotFoundException) {
            return ['error' => 'No se encontró la tarea indicada.'];
        } catch (\Throwable $e) {
            Log::error('GeminiChatService: error ejecutando acción del asistente', [
                'accion' => $nombreFuncion,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Ocurrió un error inesperado al ejecutar la acción.'];
        }
    }

    private function crearTarea(User $usuario, array $args, bool $esBug): array
    {
        $projectKey = trim((string) ($args['project_key'] ?? ''));
        $summary = trim((string) ($args['summary'] ?? ''));

        if ($projectKey === '' || $summary === '') {
            return ['error' => 'Faltan datos: se necesita el proyecto (project_key) y el título de la tarea (summary).'];
        }

        if (!GpProject::where('key', $projectKey)->exists()) {
            return ['error' => "No existe ningún proyecto con la key \"{$projectKey}\"."];
        }

        // Misma regla que GestionProyectosController::store(): el gate 'crear' deja
        // pasar al Tester solo para registrar bugs (tipo "Error").
        if (!$usuario->can('crear', $projectKey)) {
            return ['error' => 'No tienes permiso para crear tareas en ese proyecto.'];
        }

        $tienePermisoCompleto = $usuario->hasRole(['admin', 'super-admin', 'super_admin'])
            || $usuario->can('gestion-proyectos.admin')
            || GpSpaceMember::canWrite($usuario->id, $projectKey);

        $issueType = $esBug ? 'Error' : 'Tarea';

        if (!$tienePermisoCompleto && !$esBug) {
            return ['error' => 'Como Tester, solo puedes registrar bugs (usa registrar_bug), no crear tareas normales.'];
        }

        $data = [
            'project_key' => $projectKey,
            'summary' => $summary,
            'issue_type' => $issueType,
            'priority' => $args['priority'] ?? null,
            'description' => $args['descripcion'] ?? null,
            // start_date es obligatorio en gp_proyectos; si el chat no la recibe,
            // se asume que la tarea arranca hoy (igual que haría un usuario
            // creándola desde el formulario sin tocar ese campo).
            'start_date' => now()->toDateString(),
            'fecha_limite' => $args['fecha_limite'] ?? null,
            'creator_id' => $usuario->id,
            'reporter_id' => $usuario->id,
        ];

        if (!empty($args['assignee_nombre'])) {
            $asignado = $this->resolverUsuarioPorNombre($projectKey, (string) $args['assignee_nombre']);
            if ($asignado === null) {
                return ['error' => "No encontré a nadie llamado \"{$args['assignee_nombre']}\" en ese proyecto."];
            }
            $data['assignee_id'] = $asignado->id;
        }

        /** @var ProyectoServiceInterface $service */
        $service = app(ProyectoServiceInterface::class);
        $proyecto = $service->create($data);

        return [
            'ok' => true,
            'key' => $proyecto->key,
            'message' => ($esBug ? "Bug registrado: {$proyecto->key} — {$proyecto->summary}." : "Tarea creada: {$proyecto->key} — {$proyecto->summary}.")
                . ' Puedes verla en el tablero Kanban del proyecto.',
        ];
    }

    private function actualizarEstado(User $usuario, array $args): array
    {
        $key = trim((string) ($args['tarea_key'] ?? ''));
        $estado = trim((string) ($args['nuevo_estado'] ?? ''));

        if ($key === '' || $estado === '') {
            return ['error' => 'Faltan datos: se necesita la key de la tarea y el nuevo estado.'];
        }

        /** @var ProyectoServiceInterface $service */
        $service = app(ProyectoServiceInterface::class);
        $proyecto = $service->update($key, ['status' => $estado], $usuario);

        return [
            'ok' => true,
            'key' => $proyecto->key,
            'message' => "Tarea {$proyecto->key} actualizada al estado \"{$proyecto->status}\".",
        ];
    }

    private function eliminarTarea(User $usuario, array $args): array
    {
        $key = trim((string) ($args['tarea_key'] ?? ''));

        if ($key === '') {
            return ['error' => 'Falta la key de la tarea a eliminar.'];
        }

        /** @var ProyectoServiceInterface $service */
        $service = app(ProyectoServiceInterface::class);
        $service->delete($key, $usuario);

        return [
            'ok' => true,
            'key' => $key,
            'message' => "Tarea {$key} eliminada.",
        ];
    }

    /** Busca por nombre (parcial, insensible a mayúsculas) entre los miembros del espacio. */
    private function resolverUsuarioPorNombre(string $projectKey, string $nombre): ?User
    {
        $memberIds = GpSpaceMember::where('project_key', $projectKey)->pluck('user_id');

        return User::whereIn('id', $memberIds)
            ->where('name', 'like', '%' . $nombre . '%')
            ->first();
    }

    private function construirSystemInstruction(User $usuario): string
    {
        $contextoTareas = $this->construirContextoTareas($usuario);
        $contextoProyectos = $this->construirContextoProyectos($usuario);

        return <<<TEXT
        Eres el asistente conversacional integrado en Zync, una plataforma de gestión de
        proyectos de software. SOLO ayudas con la gestión de proyectos y tareas dentro de
        esta plataforma: consultar, crear o actualizar tareas, cambiar su estado, eliminarlas
        o registrar bugs. No respondas preguntas de cultura general, historia, personas
        públicas u otros temas sin relación con la plataforma (por ejemplo "¿en qué año
        nació...?"); en esos casos responde amablemente que solo puedes ayudar con la
        gestión de proyectos de Zync.

        Responde siempre en español, de forma breve y concreta.

        Tareas actualmente asignadas al usuario que te habla:
        {$contextoTareas}

        Proyectos/espacios donde participa este usuario:
        {$contextoProyectos}

        Puedes EJECUTAR acciones reales usando las funciones disponibles (crear_tarea,
        actualizar_estado_tarea, eliminar_tarea, registrar_bug) cuando el usuario lo pida
        explícitamente y tengas los datos necesarios (como mínimo el proyecto y el título
        para crear, o la key de la tarea para actualizar/eliminar). Si falta un dato
        obligatorio, pídeselo antes de llamar a la función; no inventes valores. Si la
        acción falla por permisos u otro motivo, explica el motivo con claridad.
        TEXT;
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
                return "- [{$tarea->key}] {$tarea->summary} — estado: {$tarea->status}, prioridad: {$tarea->priority}{$fecha}, proyecto: {$tarea->project}";
            })
            ->implode("\n");
    }

    /** Lista los proyectos/espacios donde el usuario es miembro o propietario. */
    private function construirContextoProyectos(User $usuario): string
    {
        if ($usuario->hasRole(['admin', 'super-admin', 'super_admin']) || $usuario->can('gestion-proyectos.admin')) {
            $proyectos = GpProject::where('active', true)->get(['key', 'name']);
        } else {
            $keys = GpSpaceMember::where('user_id', $usuario->id)->pluck('project_key');
            $proyectos = GpProject::whereIn('key', $keys)->where('active', true)->get(['key', 'name']);
        }

        if ($proyectos->isEmpty()) {
            return 'El usuario no participa en ningún proyecto activo.';
        }

        return $proyectos
            ->map(fn (GpProject $p) => "- [{$p->key}] {$p->name}")
            ->implode("\n");
    }
}
