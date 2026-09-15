<?php

namespace Modules\GestionProyectos\Http\Controllers\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\GestionProyectos\Services\DTOs\ProyectoDTO;

/**
 * API REST del CRUD de issues SCRUM. Autenticada por token Bearer (AuthGpApiToken).
 *
 * Reutiliza ProyectoService (CRUD + reglas de roles/estados) y ProyectoDTO (salida).
 * NO duplica lógica: la autorización por espacio/rol y las reglas de transición
 * (críticos, terminal, evidencia, reprogramado, XOR equipo/usuario) las aplica el
 * service tal cual la web. La respuesta omite `custom_fields` (fuera de alcance).
 */
class ScrumApiController extends Controller
{
    public function __construct(
        private ProyectoServiceInterface $service,
    ) {}

    // GET /api/gestion-proyectos/me  → confirma quién soy según el token.
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
            ],
        ]);
    }

    // GET /api/gestion-proyectos/spaces  → espacios visibles + su `key` (para usar en /issues).
    public function spaces(Request $request): JsonResponse
    {
        $user = $request->user();
        $esAdmin = $user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin');

        // null = admin global (ve todos); array = keys de espacios donde es miembro/propietario.
        $keys = GpSpaceMember::visibleProjectKeys($user);

        $projects = GpProject::query()
            ->when($keys !== null, fn ($q) => $q->whereIn('key', $keys))
            ->orderBy('name')
            ->get(['key', 'name', 'space_type', 'prefix', 'owner_id']);

        $data = $projects->map(fn (GpProject $p) => [
            'key'        => $p->key,
            'name'       => $p->name,
            'space_type' => $p->space_type,
            'prefix'     => $p->prefix,
            'role'       => $esAdmin
                ? 'admin_global'
                : (GpSpaceMember::roleInSpace($user->id, $p->key)
                    ?? ((int) $p->owner_id === $user->id ? 'propietario' : null)),
        ])->values();

        return response()->json(['data' => $data]);
    }

    // GET /api/gestion-proyectos/issues?project=ERP&statuses[]=...&search=...&page=1&per_page=50
    public function index(Request $request): JsonResponse
    {
        $user       = $request->user();
        $projectKey = (string) $request->query('project', '');

        if ($projectKey === '') {
            return response()->json(['message' => 'El parámetro "project" es obligatorio.'], 422);
        }
        if (!$this->canAccessProject($user, $projectKey)) {
            return response()->json(['message' => 'No tenés acceso a ese espacio.'], 403);
        }

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));

        $filters = [
            'project'    => $projectKey,
            'statuses'   => (array) $request->query('statuses', []),
            'priorities' => (array) $request->query('priorities', []),
            'labels'     => (array) $request->query('labels', []),
            'search'     => (string) $request->query('search', ''),
            'order_by'   => (string) $request->query('order_by', 'created_at DESC'),
        ];

        $result = $this->service->getPaginated($filters, $page, $perPage);

        return response()->json([
            'data' => array_map(fn ($p) => $this->toApi($p), $result['data']),
            'meta' => [
                'total'        => $result['total']        ?? null,
                'per_page'     => $perPage,
                'current_page' => $result['current_page'] ?? $page,
                'last_page'    => $result['last_page']    ?? null,
            ],
        ]);
    }

    // GET /api/gestion-proyectos/issues/{key}
    public function show(Request $request, string $key): JsonResponse
    {
        $proyecto = Proyecto::where('key', $key)->first();

        if (!$proyecto) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
        if (!$this->canAccessProject($request->user(), $proyecto->project)) {
            return response()->json(['message' => 'No tenés acceso a esa tarea.'], 403);
        }

        return response()->json($this->toApi($this->loadForDto($proyecto)));
    }

    // POST /api/gestion-proyectos/issues
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_key'         => ['required', 'string', 'exists:gp_projects,key'],
            'summary'             => ['required', 'string', 'max:500'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'issue_type'          => ['nullable', 'string', 'max:60'],
            'description'         => ['nullable', 'string', 'max:10000'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'dias_estimados'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fecha_entrega'       => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'    => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'impacto'             => ['nullable', 'array'],
            'impacto.*'           => ['string', 'max:100'],
            'software'            => ['nullable', 'string', 'max:100'],
            'entorno'             => ['nullable', 'string', 'max:100'],
            'solicitado_por'      => ['nullable', 'string', 'max:255'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'           => ['nullable', 'string', 'max:100'],
            'assignee_account_id' => ['nullable', 'string', 'max:30'],
            'reporter_account_id' => ['nullable', 'string', 'max:30'],
            'team_id'             => ['nullable', 'integer', 'exists:gp_teams,id'],
        ]);

        // Autorización: crear en ese espacio (mismo Gate que la web).
        if (!$request->user()->can('crear', $validated['project_key'])) {
            return response()->json(['message' => 'No tenés permiso para crear tareas en ese espacio.'], 403);
        }

        $data = $validated;
        $data['assignee_id'] = $this->resolveUserId($validated['assignee_account_id'] ?? null);
        $data['creator_id']  = $request->user()->id;
        $data['reporter_id'] = empty($validated['reporter_account_id'])
            ? $request->user()->id
            : $this->resolveUserId($validated['reporter_account_id']);
        unset($data['assignee_account_id'], $data['reporter_account_id']);

        try {
            $proyecto = $this->service->create($data);
            return response()->json($this->toApi($this->loadForDto($proyecto)), 201);
        } catch (GestionProyectosException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/gestion-proyectos/issues/{key}
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'summary'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'               => ['sometimes', 'nullable', 'string', 'max:60'],
            'priority'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'issue_type'           => ['sometimes', 'nullable', 'string', 'max:60'],
            'description'          => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_date'           => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'dias_estimados'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'fecha_limite'         => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_entrega'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'     => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_reprogramacion' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'labels'               => ['sometimes', 'nullable', 'array'],
            'labels.*'             => ['string', 'max:100'],
            'impacto'              => ['sometimes', 'nullable', 'array'],
            'impacto.*'            => ['string', 'max:100'],
            'software'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'entorno'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'solicitado_por'       => ['sometimes', 'nullable', 'string', 'max:255'],
            // Opcional siempre: el catálogo por espacio (gp_categorias) lo alimenta el service al vuelo.
            'categoria'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'assignee_account_id'  => ['sometimes', 'nullable', 'string', 'max:30'],
            'reporter_account_id'  => ['sometimes', 'nullable', 'string', 'max:30'],
            'team_id'              => ['sometimes', 'nullable', 'integer', 'exists:gp_teams,id'],
        ]);

        $fields = $validated;
        foreach (['assignee_account_id' => 'assignee_id', 'reporter_account_id' => 'reporter_id'] as $from => $to) {
            if (array_key_exists($from, $fields)) {
                $fields[$to] = $this->resolveUserId($fields[$from]);
                unset($fields[$from]);
            }
        }

        if (empty($fields)) {
            return response()->json(['message' => 'No se enviaron campos para actualizar.'], 422);
        }

        try {
            // El service autoriza (policy update) y aplica todas las reglas de estado.
            $proyecto = $this->service->update($key, $fields, $request->user());
            return response()->json($this->toApi($this->loadForDto($proyecto)));
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (GestionProyectosException $e) {
            return response()->json(['message' => $e->getMessage(), 'context' => $e->getContext() ?? null], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
    }

    // DELETE /api/gestion-proyectos/issues/{key}
    public function destroy(Request $request, string $key): JsonResponse
    {
        try {
            $this->service->delete($key, $request->user());
            return response()->json(['message' => "Tarea {$key} eliminada."]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Serializa al shape del DTO, omitiendo los custom_fields (fuera de alcance de la API). */
    private function toApi(Proyecto $p): array
    {
        $arr = ProyectoDTO::fromModel($p);
        unset($arr['custom_fields']);
        return $arr;
    }

    /** Carga las relaciones que necesita el DTO para una respuesta completa. */
    private function loadForDto(Proyecto $p): Proyecto
    {
        return $p->load(['assignee', 'reporter', 'creator', 'aprobadoPor', 'team'])
                 ->loadCount('subTareas');
    }

    /** account_id (string) → user_id (int). El frontend/DTO usa (string) el id local. */
    private function resolveUserId(?string $accountId): ?int
    {
        if ($accountId === null || $accountId === '' || $accountId === 'null') {
            return null;
        }
        $id = (int) $accountId;
        return $id > 0 ? $id : null;
    }

    /** ¿El usuario puede ver ese espacio? Admin global, miembro, o propietario. */
    private function canAccessProject($user, string $projectKey): bool
    {
        if ($user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->can('gestion-proyectos.admin')) {
            return true;
        }
        if (GpSpaceMember::roleInSpace($user->id, $projectKey) !== null) {
            return true;
        }
        return GpProject::where('key', $projectKey)->where('owner_id', $user->id)->exists();
    }
}
