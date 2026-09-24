<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Http\Requests\GestionProyectosIndexRequest;
use Modules\GestionProyectos\Http\Requests\GestionProyectosStoreProjectRequest;
use Modules\GestionProyectos\Http\Requests\GestionProyectosStoreRequest;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\GpAuditLog;
use Modules\GestionProyectos\Models\GpCustomField;
use Modules\GestionProyectos\Models\GpCategoria;
use Modules\GestionProyectos\Models\GpLabel;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceCategory;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\GpSubTarea;
use Modules\GestionProyectos\Models\GpSubTareaHistorial;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Services\Contracts\CustomFieldServiceInterface;
use Modules\GestionProyectos\Services\Contracts\ProyectoServiceInterface;
use Modules\GestionProyectos\Services\DTOs\ProyectoDTO;

class GestionProyectosController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ProyectoServiceInterface $service,
        protected CustomFieldServiceInterface $customFieldService,
    ) {}

    // =========================================================================
    // index
    // =========================================================================

    public function index(GestionProyectosIndexRequest $request): InertiaResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $filters = $request->validated();
        // Acepta `page` (nativo) o `next_page_token` (alias del frontend copiado de Seguimiento)
        $page    = (int) ($filters['page'] ?? $filters['next_page_token'] ?? 1);
        $perPage = (int) ($filters['maxResults'] ?? config('gestion-proyectos.default_max_results', 50));

        // Proyectos disponibles — sólo los que existen en la BD
        $allProjects = [];
        try {
            $allProjects = $this->service->getProjects();
        } catch (\Throwable $e) {
            Log::warning('[GestionProyectos] No se pudieron cargar los proyectos', ['msg' => $e->getMessage()]);
        }

        // Visibilidad por espacio: el usuario solo ve los espacios donde es miembro
        // vigente o propietario; los admin globales ven todos (null = sin filtrar).
        // Regla centralizada en GpSpaceMember::visibleProjectKeys (la usan también
        // misPendientes y los endpoints por {key}).
        $user = auth()->user();
        $visibleKeys = GpSpaceMember::visibleProjectKeys($user);

        if ($visibleKeys === null) {
            $projects = $allProjects;
        } else {
            $allowed  = array_flip($visibleKeys);
            $projects = array_values(array_filter($allProjects, fn ($p) => isset($allowed[$p['key']])));
        }

        // Proyecto activo: el pedido (solo si el usuario pertenece a él), o el ÚLTIMO
        // espacio visitado (guardado en sesión, si sigue siendo accesible), o el primero.
        // Así un ?project= ajeno no carga datos de otros espacios, y al volver al módulo
        // desde otra ruta se mantiene el espacio donde estabas.
        $allowedKeys = array_column($projects, 'key');
        $requestedProject = $filters['project'] ?? null;
        $lastProject = $request->session()->get('gp.last_project');

        if ($requestedProject && in_array($requestedProject, $allowedKeys, true)) {
            $activeProject = $requestedProject;
        } elseif ($lastProject && in_array($lastProject, $allowedKeys, true)) {
            $activeProject = $lastProject;
        } else {
            $activeProject = $projects[0]['key'] ?? null;
        }

        // Recordar el espacio activo para la próxima vez que se entre sin ?project.
        if ($activeProject) {
            $request->session()->put('gp.last_project', $activeProject);
        }

        // Si no hay proyectos en la BD todavía, renderizar vista vacía
        if (!$activeProject) {
            return Inertia::render('GestionProyectos/Index', [
                'issues'            => [],
                'projects'          => [],
                'customFields'      => [],
                'columnPreferences' => $this->customFieldService->buildDefaultColumns(),
                'catalogColumns'    => $this->customFieldService->getCatalogColumns(),
                'canEdit'           => auth()->user()->hasRole(['admin', 'super-admin', 'super_admin']) || auth()->user()->can('gestion-proyectos.admin'),
                'spaceMemberRole'   => null,
                'spaceTeams'        => [],
                'spaceCategories'   => GpSpaceCategory::orderBy('name')->get(['id', 'name'])->values()->all(),
                'allLabels'         => [],
                'spaceLabels'       => [],
                'meta'              => [
                    'total' => 0, 'count' => 0, 'max_results' => $perPage,
                    'is_last' => true, 'current_page' => 1, 'last_page' => 1,
                    'next_page_token' => null, 'jql' => '',
                ],
                'filters' => ['project' => '', 'statuses' => [], 'priorities' => [], 'labels' => [], 'search' => '', 'group_by' => '', 'order_by' => 'created_at DESC'],
                'options' => [
                    'statuses'  => [],
                    'priorities'=> [],
                    'labels'    => [],
                    'group_by'  => [
                        ['value' => 'status',     'label' => 'Estado'],
                        ['value' => 'assignee',   'label' => 'Persona asignada'],
                        ['value' => 'priority',   'label' => 'Prioridad'],
                        ['value' => 'labels',     'label' => 'Etiquetas'],
                        ['value' => 'issue_type', 'label' => 'Tipo Actividad'],
                        ['value' => 'entorno',    'label' => 'Entorno'],
                        ['value' => 'impacto',    'label' => 'Impacto'],
                    ],
                ],
                'jira_error' => null,
            ]);
        }

        $paginated = $this->service->getPaginated(array_merge($filters, ['project' => $activeProject]), $page, $perPage);

        $issues   = ProyectoDTO::fromCollection($paginated['data']);
        $lastPage = $paginated['last_page'];
        $isLast   = $page >= $lastPage;

        // Estados del proyecto (dinámicos desde config)
        $dynamicStatuses = $this->service->getStatuses();

        // Etiquetas del espacio activo (catálogo por-espacio) — opciones del filtro.
        $dynamicLabels = GpLabel::forProject($activeProject)->orderBy('name')->pluck('name')->all();

        // Campos personalizados del proyecto activo
        $customFields = [];
        try {
            $customFields = $this->customFieldService->getCustomFields($activeProject);
        } catch (\Throwable $e) {
            Log::warning('[GestionProyectos] No se pudieron cargar custom fields', ['msg' => $e->getMessage()]);
        }

        // Preferencias de columnas del usuario autenticado
        $columnPreferences = [];
        try {
            $columnPreferences = $this->customFieldService->getColumnPreferences(
                auth()->id(),
                $activeProject
            );
        } catch (\Throwable $e) {
            Log::warning('[GestionProyectos] No se pudieron cargar column preferences', ['msg' => $e->getMessage()]);
        }

        $canEdit = GpSpaceMember::canWrite(auth()->id(), $activeProject);

        // Equipos del espacio activo
        $projectModel = GpProject::where('key', $activeProject)->first();
        $spaceTeams = $projectModel 
            ? $projectModel->teams()->with('members')->get()->map(fn ($t) => $t->toFrontend())->values()->all()
            : [];

        // Rol del usuario autenticado en el espacio activo
        $spaceMemberRole = GpSpaceMember::roleInSpace(auth()->id(), $activeProject);
        // Admin global → rol virtual 'administrador'
        if ($spaceMemberRole === null && auth()->user()->hasRole('admin')) {
            $spaceMemberRole = 'administrador';
        }
        // Propietario del proyecto → siempre 'propietario' (corrige registros lector/null residuales)
        $projectOwnerId = GpProject::where('key', $activeProject)->value('owner_id');
        if ($projectOwnerId && (int) $projectOwnerId === auth()->id()) {
            $spaceMemberRole = 'propietario';
        }

        // IDs de usuarios con rol "admin" en el espacio ACTIVO, para pintar la insignia "Admin"
        // en sus avatares de la tabla: administradores/propietarios del espacio (no suspendidos)
        // + el propietario por owner_id + los admins globales (permiso gestion-proyectos.admin).
        $spaceAdminIds = GpSpaceMember::where('project_key', $activeProject)
            ->where('suspended', false)
            ->whereIn('role', ['administrador', 'propietario'])
            ->pluck('user_id')
            ->all();
        if ($projectOwnerId) {
            $spaceAdminIds[] = (int) $projectOwnerId;
        }
        $spaceAdminIds = array_values(array_unique(array_map('intval', array_merge(
            $spaceAdminIds,
            \Modules\User\Models\User::permission('gestion-proyectos.admin')->pluck('id')->all()
        ))));

        return Inertia::render('GestionProyectos/Index', [
            'issues'            => $issues,
            'projects'          => $projects,
            'customFields'      => $customFields,
            'columnPreferences' => $columnPreferences,
            'catalogColumns'    => $this->customFieldService->getCatalogColumns(),
            'canEdit'           => $canEdit,
            'canManage'         => GpSpaceMember::canManage(auth()->id(), $activeProject),
            'spaceMemberRole'   => $spaceMemberRole,
            'spaceAdminIds'     => $spaceAdminIds,
            'spaceTeams'        => $spaceTeams,
            'spaceCategories'   => GpSpaceCategory::orderBy('name')->get(['id', 'name'])->values()->all(),
            // Etiquetas propias del espacio activo (mismo set para picker y filtros).
            'allLabels'         => GpLabel::forProject($activeProject)->orderBy('name')->get(['id', 'name'])->values()->all(),
            'spaceLabels'       => GpLabel::forProject($activeProject)->orderBy('name')->get(['id', 'name'])->values()->all(),
            // Categorias ya usadas en el espacio: alimentan el desplegable del campo
            // Categoria. El catalogo crece solo (ProyectoService::ensureCategoria).
            'spaceCategorias'   => GpCategoria::forProject($activeProject)->orderBy('name')->get(['id', 'name'])->values()->all(),
            'meta'              => [
                'total'        => $paginated['total'],
                'count'        => count($issues),
                'max_results'  => $perPage,
                'is_last'      => $isLast,
                'current_page' => $paginated['current_page'],
                'last_page'    => $lastPage,
                // Compatibilidad con el frontend de Seguimiento
                'next_page_token' => $isLast ? null : (string) ($page + 1),
                'jql'             => "project = \"{$activeProject}\"",
            ],
            'filters' => [
                'project'     => $activeProject,
                'statuses'    => $filters['statuses']    ?? [],
                'assignees'   => $filters['assignees']   ?? [],
                'priorities'  => $filters['priorities']  ?? [],
                'issue_types' => $filters['issue_types'] ?? [],
                'labels'      => $filters['labels']      ?? [],
                'search'      => $filters['search']      ?? '',
                'group_by'    => $filters['group_by']    ?? null,
                'order_by'    => $filters['order_by']    ?? 'created_at DESC',
            ],
            'options' => [
                'statuses'          => $dynamicStatuses,
                'critical_statuses' => config('gestion-proyectos.critical_statuses', ['Finalizado', 'Reprogramado']),
                'terminal_statuses' => config('gestion-proyectos.terminal_statuses', ['Finalizado']),
                'priorities'        => config('gestion-proyectos.priorities', []),
                'impacto_values'    => config('gestion-proyectos.impacto_values', []),
                'issue_types' => config('gestion-proyectos.issue_types', []),
                'labels'      => $dynamicLabels,
                'group_by'    => [
                    ['value' => 'status',     'label' => 'Estado'],
                    ['value' => 'assignee',   'label' => 'Persona asignada'],
                    ['value' => 'priority',   'label' => 'Prioridad'],
                    ['value' => 'labels',     'label' => 'Etiquetas'],
                    ['value' => 'issue_type', 'label' => 'Tipo Actividad'],
                    ['value' => 'entorno',    'label' => 'Entorno'],
                    ['value' => 'impacto',    'label' => 'Impacto'],
                ],
            ],
            'jira_error' => null,
        ]);
    }

    // =========================================================================
    // store
    // =========================================================================

    public function store(GestionProyectosStoreRequest $request): RedirectResponse
    {
        $this->authorize('crear', $request->project_key);

        $data = $request->validated();

        // Tester: el gate 'crear' lo deja pasar solo para registrar bugs. Si no tiene
        // escritura completa del espacio, exigimos que el tipo de tarea sea "Error".
        $actor = $request->user();
        $tienePermisoCompleto = $actor->hasRole(['admin', 'super-admin', 'super_admin'])
            || $actor->can('gestion-proyectos.admin')
            || \Modules\GestionProyectos\Models\GpSpaceMember::canWrite($actor->id, $data['project_key']);

        if (!$tienePermisoCompleto && ($data['issue_type'] ?? null) !== 'Error') {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Como Tester, solo puedes registrar bugs (tipo "Error").'])
                ->withInput();
        }

        // Resolver account_id (string) → user_id (int)
        $data['assignee_id'] = $this->resolveUserId($data['assignee_account_id'] ?? null);
        $data['creator_id']  = $request->user()->id;

        if (empty($data['reporter_account_id'])) {
            $data['reporter_id'] = $request->user()->id;
        } else {
            $data['reporter_id'] = $this->resolveUserId($data['reporter_account_id']);
        }

        // Si no se especifica assignee, la tarea queda en Backlog (NULL).
        // Patrón SCRUM: cualquier desarrollador puede tomarla; no atamos al reporter.

        try {
            $proyecto = $this->service->create($data);

            return redirect()
                ->route('gestion-proyectos.index', ['project' => $data['project_key']])
                ->with('success', "Proyecto {$proyecto->key} creado exitosamente.");
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    // =========================================================================
    // update (inline / PATCH)
    // =========================================================================

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'summary'               => ['nullable', 'string', 'max:500'],
            'status'                => ['nullable', 'string', 'max:60'],
            'priority'              => ['nullable', 'string', 'max:30'],
            'assignee_account_id'   => ['nullable', 'string', 'max:30'],
            'reporter_account_id'   => ['nullable', 'string', 'max:30'],
            'validado_por_account_id' => ['nullable', 'string', 'max:30'],
            'solicitado_por'        => ['nullable', 'string', 'max:255'],
            'categoria'             => ['nullable', 'string', 'max:100'],
            'fecha_limite'          => ['nullable', 'date_format:Y-m-d'],
            'start_date'            => ['nullable', 'date_format:Y-m-d'],
            'labels'                => ['nullable', 'array'],
            'labels.*'              => ['string', 'max:100'],
            'software'              => ['nullable', 'string', 'max:100'],
            'entorno'               => ['nullable', 'string', 'max:100'],
            'impacto'               => ['nullable', 'array'],
            'impacto.*'             => ['string', Rule::in(config('gestion-proyectos.impacto_values', []))],
            'dias_estimados'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fecha_entrega'         => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'      => ['nullable', 'date_format:Y-m-d'],
            'fecha_reprogramacion'  => ['nullable', 'date_format:Y-m-d'],
            'team_id'               => ['nullable', 'integer', 'exists:gp_teams,id'],
            // Inline edit de campo personalizado
            'custom_field_id'       => ['nullable', 'integer', 'exists:gp_custom_fields,id'],
            'custom_field_value'    => ['nullable', 'string', 'max:2000'],
        ]);

        // Manejo especial: actualizar campo personalizado
        if (!empty($validated['custom_field_id'])) {
            $proyecto = Proyecto::where('key', $key)->firstOrFail();

            // Validar autorización para editar la tarea (inline → solo propietario)
            $this->authorize('inlineEdit', $proyecto);

            // Estado terminal: una tarea Finalizada/Cancelada es inmutable. Esta rama retorna
            // antes de las validaciones del service, así que el bloqueo terminal se replica aquí
            // para que no se puedan alterar campos personalizados de una tarea cerrada.
            $terminals = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
            if (in_array($proyecto->status, $terminals, true)) {
                return response()->json([
                    'error' => "La tarea está en estado \"{$proyecto->status}\" (terminal) y no puede ser modificada.",
                ], 422);
            }

            // Validar que el campo pertenezca al proyecto/espacio de la tarea
            $field = GpCustomField::where('id', $validated['custom_field_id'])
                ->where('project_key', $proyecto->project)
                ->first();

            if (!$field) {
                return response()->json(['error' => 'El campo personalizado no pertenece a este espacio.'], 422);
            }

            \Modules\GestionProyectos\Models\GpCustomFieldValue::updateOrCreate(
                ['proyecto_id' => $proyecto->id, 'custom_field_id' => $validated['custom_field_id']],
                ['value' => $validated['custom_field_value'] ?? null]
            );
            return response()->json(['message' => 'Campo personalizado actualizado.']);
        }

        // Manejo especial: "Validado Por" (valida el "aprobado por"). Campo libre, sin lógica:
        //  - Solo lo setean Aprobador/Implementador/Propietario/Administrador o admin global.
        //  - Solo cuando la actividad ya está FINALIZADA (antes, nadie puede validar).
        //  - Editable aunque sea terminal (no pasa por la inmutabilidad del service ni el gate owner).
        if (array_key_exists('validado_por_account_id', $validated)) {
            $proyecto = Proyecto::where('key', $key)->firstOrFail();
            $actor    = auth()->user();

            $puede = $actor->hasRole(['admin', 'super-admin', 'super_admin'])
                || $actor->can('gestion-proyectos.admin')
                || GpSpaceMember::canApprove($actor->id, $proyecto->project);
            if (!$puede) {
                return response()->json(['error' => 'Solo el Aprobador, Implementador, Propietario o Administrador del espacio pueden validar.'], 403);
            }

            if ($proyecto->status !== 'Finalizado') {
                return response()->json(['error' => 'Solo se puede registrar "Validado Por" cuando la actividad está Finalizada.'], 422);
            }

            $proyecto->update([
                'validado_por_id' => ($validated['validado_por_account_id'] === 'null')
                    ? null
                    : $this->resolveUserId($validated['validado_por_account_id']),
            ]);

            $proyecto->load('validadoPor:id,name,avatar');
            $proyecto->loadCount(['auditLogs as audit_changes_count' => fn ($q) => $q->where('action', '!=', 'created')]);
            return response()->json([
                'message'      => 'Validado Por actualizado.',
                'validado_por' => $proyecto->validadoPor ? [
                    'account_id'   => (string) $proyecto->validadoPor->id,
                    'display_name' => $proyecto->validadoPor->name,
                    'avatar_url'   => $proyecto->validadoPor->avatar_url,
                ] : null,
                'audit_changes_count' => (int) $proyecto->audit_changes_count,
            ]);
        }

        $proyecto = Proyecto::where('key', $key)->firstOrFail();

        $fields = [];

        if (isset($validated['summary']))         $fields['summary']         = $validated['summary'];
        if (isset($validated['status']))          $fields['status']          = $validated['status'];
        if (isset($validated['priority']))        $fields['priority']        = $validated['priority'];
        if (isset($validated['fecha_limite']))    $fields['fecha_limite']    = $validated['fecha_limite'];
        if (isset($validated['start_date']))      $fields['start_date']      = $validated['start_date'];
        if (isset($validated['labels']))          $fields['labels']          = $validated['labels'];
        if (isset($validated['solicitado_por']))  $fields['solicitado_por']  = $validated['solicitado_por'];
        // array_key_exists y no isset: la categoria se debe poder VACIAR (null) desde
        // la edicion inline, e isset() descartaria justo ese caso.
        if (array_key_exists('categoria', $validated)) $fields['categoria']  = $validated['categoria'];
        if (isset($validated['software']))        $fields['software']        = $validated['software'];
        if (isset($validated['entorno']))         $fields['entorno']         = $validated['entorno'];
        if (isset($validated['impacto']))         $fields['impacto']         = $validated['impacto'];
        if (isset($validated['dias_estimados']))  $fields['dias_estimados']  = $validated['dias_estimados'];
        if (array_key_exists('fecha_entrega', $validated)) $fields['fecha_entrega'] = $validated['fecha_entrega'];
        if (array_key_exists('fecha_aprobacion', $validated)) $fields['fecha_aprobacion'] = $validated['fecha_aprobacion'];
        if (array_key_exists('fecha_reprogramacion', $validated)) $fields['fecha_reprogramacion'] = $validated['fecha_reprogramacion'];
        if (array_key_exists('team_id', $validated)) $fields['team_id'] = $validated['team_id'];

        if (isset($validated['assignee_account_id'])) {
            $fields['assignee_id'] = ($validated['assignee_account_id'] === 'null')
                ? null
                : $this->resolveUserId($validated['assignee_account_id']);
        }

        if (isset($validated['reporter_account_id'])) {
            // El "Solicitado Por" que muestra la tabla sale del TEXTO `solicitado_por`
            // (ProyectoDTO::mapSolicitante lo prioriza sobre el reporter). Al cambiar el
            // reporter inline hay que sincronizar ese texto con el nombre del nuevo usuario;
            // si no, queda "pegado" el nombre anterior y el cambio no se refleja al recargar.
            // No se pisa si el request manda `solicitado_por` explícito (flujo de import/edición libre).
            if ($validated['reporter_account_id'] === 'null') {
                $fields['reporter_id'] = null;
                if (!array_key_exists('solicitado_por', $validated)) {
                    $fields['solicitado_por'] = null;   // sin reporter → cae al fallback (Sin solicitante)
                }
            } else {
                $uid = $this->resolveUserId($validated['reporter_account_id']);
                $fields['reporter_id'] = $uid;
                if (!array_key_exists('solicitado_por', $validated)) {
                    $fields['solicitado_por'] = \Modules\User\Models\User::find($uid)?->name;
                }
            }
        }

        if (empty($fields)) {
            return response()->json(['error' => 'No hay campos para actualizar.'], 422);
        }

        // Autorización inline diferenciada:
        //  - Transición de ESTADO (status / fecha_reprogramacion): es una acción de flujo que
        //    pueden hacer escritores y aprobadores. El ProyectoService valida can('update') y las
        //    reglas de estados críticos (Finalizado/Reprogramado/Cancelado → solo aprobador).
        //  - Cualquier OTRO campo: edición de datos, restringida al propietario del espacio (o admin global).
        $soloEstado = empty(array_diff(array_keys($fields), ['status', 'fecha_reprogramacion']));
        if (!$soloEstado) {
            $this->authorize('inlineEdit', $proyecto);
        }

        // La Fecha Límite es solo un margen estimado (sincronizado con días estimados):
        // las fechas de Subida a Stage/Producción pueden superarla sin restricción.

        try {
            $updated = $this->service->update($key, $fields);
            // El "Aprobado Por" se setea solo al pasar a Finalizado (hook del modelo); lo devolvemos
            // para que la celda se llene al instante sin recargar.
            $updated->load('aprobadoPor:id,name,avatar');
            // Conteo fresco de cambios auditados (el ProyectoObserver ya escribió la entrada de este
            // PATCH): lo devolvemos para que el badge del botón de "Seguimiento" se actualice sin F5.
            $updated->loadCount(['auditLogs as audit_changes_count' => fn ($q) => $q->where('action', '!=', 'created')]);
            return response()->json([
                'message'          => "Proyecto {$key} actualizado exitosamente.",
                'dias_estimados'   => $updated->dias_estimados,
                'fecha_limite'     => $updated->fecha_limite?->toDateString() ?? $updated->getRawOriginal('fecha_limite'),
                // Fechas de etapa auto-sincronizadas con el estado (Stage/Producción).
                'fecha_entrega'    => $updated->fecha_entrega?->toDateString() ?? $updated->getRawOriginal('fecha_entrega'),
                'fecha_aprobacion' => $updated->fecha_aprobacion?->toDateString() ?? $updated->getRawOriginal('fecha_aprobacion'),
                'aprobado_por'     => $updated->aprobadoPor ? [
                    'account_id'   => (string) $updated->aprobadoPor->id,
                    'display_name' => $updated->aprobadoPor->name,
                    'avatar_url'   => $updated->aprobadoPor->avatar_url,
                ] : null,
                'audit_changes_count' => (int) $updated->audit_changes_count,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (GestionProyectosException $e) {
            $response = ['error' => $e->getMessage()];
            if ($ctx = $e->getContext()) {
                $response = array_merge($response, $ctx);
            }
            return response()->json($response, 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // storeProject — crea un nuevo proyecto (espacio)
    // =========================================================================

    public function storeProject(GestionProyectosStoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Si quedó un proyecto soft-deleted con esta misma key, lo purgamos antes de crear
        // para evitar el 1062 de la unique index de MySQL (que no distingue deleted_at).
        // El evento `deleted` del modelo se encarga de limpiar sus pivots/miembros/labels.
        GpProject::onlyTrashed()
            ->where('key', $validated['key'])
            ->get()
            ->each(fn (GpProject $p) => $p->forceDelete());

        $project = GpProject::create([
            'key'                => $validated['key'],
            'name'               => $validated['name'],
            'description'        => $validated['description']        ?? null,
            'space_type'         => $validated['space_type']         ?? 'SCRUM_PROJECT',
            'prefix'             => $validated['prefix']             ?? strtoupper($validated['key']),
            'categoria'          => $validated['categoria']          ?? null,
            'space_category_id'  => $validated['space_category_id'] ?? null,
            'icon'               => $validated['icon']               ?? null,
            'owner_id'           => $validated['owner_id']           ?? $request->user()->id,
            'active'             => true,
            'validar_fechas_inicio' => $request->boolean('validar_fechas_inicio', true),
            'produccion_editable_finalizado' => $request->boolean('produccion_editable_finalizado', false),
        ]);

        if (!empty($validated['team_ids'])) {
            $project->teams()->sync($validated['team_ids']);
            $project->syncMembersFromTeams();
        }

        // Sembrar campos personalizados por defecto para el nuevo proyecto.
        // Fecha Registro = catalog created_at, Fecha Inicio = catalog start_date.
        $this->customFieldService->seedDefaultFields($validated['key']);

        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        // Redirige seleccionando el espacio recién creado (que aparezca activo en el switcher).
        return redirect()->route('gestion-proyectos.index', ['project' => $validated['key']])
            ->with('success', "Proyecto {$validated['key']} creado exitosamente.");
    }

    // =========================================================================
    // updateProject — actualiza un espacio existente
    // =========================================================================

    public function updateProject(Request $request, string $projectKey): RedirectResponse
    {
        $this->authorize('manage', $projectKey);

        $project = \Modules\GestionProyectos\Models\GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:150'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'space_type'         => ['nullable', 'string', 'in:SCRUM_PROJECT'],
            'prefix'             => ['nullable', 'string', 'max:10'],
            'categoria'          => ['nullable', 'string', 'max:100'],
            'space_category_id'  => ['nullable', 'integer', 'exists:gp_space_categories,id'],
            'icon'               => ['nullable', 'string', 'max:300000'],
            'owner_id'           => ['nullable', 'integer', 'exists:users,id'],
            'team_ids'           => ['nullable', 'array'],
            'team_ids.*'         => ['integer', 'exists:gp_teams,id'],
            'validar_fechas_inicio' => ['nullable', 'boolean'],
            'produccion_editable_finalizado' => ['nullable', 'boolean'],
        ], [
            'icon.max'       => 'El ícono es muy grande. La imagen no puede superar los 200 KB.',
            'team_ids.array' => 'La selección de equipos debe ser una lista válida.',
        ], [
            'team_ids' => 'equipos',
        ]);

        $project->update([
            'name'               => $validated['name'],
            'description'        => $validated['description'] ?? null,
            'space_type'         => $validated['space_type'] ?? $project->space_type,
            'prefix'             => $validated['prefix'] ?? $project->prefix,
            'categoria'          => $validated['categoria'] ?? null,
            'space_category_id'  => $validated['space_category_id'] ?? null,
            'icon'               => $validated['icon'] ?? null,
            'owner_id'           => $validated['owner_id'] ?? $project->owner_id,
            'validar_fechas_inicio' => $request->boolean('validar_fechas_inicio', $project->validar_fechas_inicio),
            'produccion_editable_finalizado' => $request->boolean('produccion_editable_finalizado', $project->produccion_editable_finalizado),
        ]);

        if (isset($validated['team_ids'])) {
            $project->teams()->sync($validated['team_ids']);
            $project->syncMembersFromTeams();
        }

        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        // Mantener al usuario en el mismo espacio editado (si no, el index cae al primero
        // y se "pierden" los registros / cambia de espacio tras el F5).
        return redirect()->route('gestion-proyectos.index', ['project' => $projectKey])
            ->with('success', "Espacio '{$project->name}' actualizado exitosamente.");
    }

    // =========================================================================
    // destroyProject — elimina (soft) un espacio
    // =========================================================================

    public function destroyProject(Request $request, string $projectKey): RedirectResponse
    {
        $user = auth()->user();

        // Alineado con GestionProyectosPolicy::manage() y GpSpaceMember::canManage()
        // que permiten tanto 'propietario' como 'administrador' del espacio.
        abort_unless(
            \Modules\GestionProyectos\Models\GpSpaceMember::canManage($user->id, $projectKey),
            403,
            'Solo el propietario o administrador del espacio puede eliminarlo.'
        );

        $project = \Modules\GestionProyectos\Models\GpProject::where('key', $projectKey)->firstOrFail();
        $name    = $project->name;
        $project->delete();

        return redirect()->route('gestion-proyectos.index')
            ->with('success', "Espacio '{$name}' eliminado correctamente.");
    }

    // =========================================================================
    // users — autocompletado
    // =========================================================================

    public function users(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        return response()->json($this->service->getAssignableUsers($query));
    }

    // =========================================================================
    // assignableUsers
    // =========================================================================

    public function assignableUsers(Request $request): JsonResponse
    {
        $projectKey = (string) $request->query('project', '');
        $query = (string) $request->query('q', '');
        return response()->json($this->service->getAssignableUsers($projectKey, $query));
    }

    /**
     * GET /mis-pendientes — todo lo asignado al usuario autenticado (actividades,
     * subactividades, reprogramaciones -Rn y tareas), de TODOS sus espacios, para el
     * panel lateral "Mis pendientes". Solo devuelve lo que tiene assignee = el propio
     * usuario (no hay fuga cross-espacio: solo ve lo suyo).
     */
    public function misPendientes(Request $request): JsonResponse
    {
        $userId     = auth()->id();
        $terminales = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
        $hoy        = now()->toDateString();

        // Filtros + paginación (20 por página) del panel "Mis pendientes".
        $perPage     = 20;
        $page        = max(1, (int) $request->query('page', 1));
        $soloActivas = $request->boolean('solo_activas', true);
        $desde       = $request->query('desde');   // 'YYYY-MM-DD' | null
        $hasta       = $request->query('hasta');   // 'YYYY-MM-DD' | null
        $q           = trim((string) $request->query('q', ''));   // búsqueda por nombre/clave
        $sort        = $request->query('sort', 'fecha');          // 'fecha' | 'prioridad'

        // Todo el filtrado/orden/paginación ocurre EN SQL (antes se hidrataba el histórico
        // completo del usuario y se ordenaba/paginaba en PHP: degradaba linealmente con
        // miles de asignaciones y cada "Cargar más" repetía el costo entero).

        // Rama A: actividades / subactividades / versiones -Rn asignadas a mí.
        // La fecha efectiva es COALESCE(fecha_reprogramacion, fecha_limite) (criterio SLA).
        $proyectos = DB::table('gp_proyectos')
            ->where('assignee_id', $userId)
            ->whereNull('deleted_at')
            ->selectRaw("`key`, summary AS title, status, priority, project, parent_key,
                         reprogramacion_root_key, creator_id,
                         DATE(COALESCE(fecha_reprogramacion, fecha_limite)) AS due_date,
                         'p' AS src");

        // Rama B: tareas asignadas a mí (sin fecha límite → nunca vencen). El INNER JOIN
        // al padre vivo descarta tareas de padre soft-deleted y aporta el espacio.
        $tareas = DB::table('gp_sub_tareas AS t')
            ->join('gp_proyectos AS p', fn ($j) => $j->on('p.key', '=', 't.parent_key')->whereNull('p.deleted_at'))
            ->where('t.assignee_id', $userId)
            ->whereNull('t.deleted_at')
            ->selectRaw("t.`key`, t.summary AS title, t.status, t.priority, p.project, t.parent_key,
                         NULL AS reprogramacion_root_key, t.creator_id,
                         NULL AS due_date,
                         't' AS src");

        // Filtro Solo activas / Todas. 'Reprogramado' es un estado durmiente: el plan
        // vigente es su versión -Rn (que ya aparece como item propio con la misma fecha
        // efectiva) → en "Solo activas" se excluye el root para no duplicar ni inflar
        // total/vencidas. En "Todas" sigue apareciendo.
        if ($soloActivas) {
            $proyectos->whereNotIn('status', $terminales)->where('status', '<>', 'Reprogramado');
            $tareas->whereNotIn('t.status', $terminales);
        }

        // Búsqueda por nombre o clave (wildcards de LIKE escapados).
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $proyectos->where(fn ($w) => $w->where('summary', 'like', $like)->orWhere('key', 'like', $like));
            $tareas->where(fn ($w) => $w->where('t.summary', 'like', $like)->orWhere('t.key', 'like', $like));
        }

        // Membresía VIGENTE (misma regla de visibilidad que el index): un ex-miembro
        // o suspendido NO sigue viendo ítems del espacio aunque conserve asignaciones.
        // null = admin global (sin filtrar); [] = sin espacios visibles (resultado vacío).
        $visibleKeys = GpSpaceMember::visibleProjectKeys(auth()->user());
        if ($visibleKeys !== null) {
            $proyectos->whereIn('project', $visibleKeys);
            $tareas->whereIn('p.project', $visibleKeys);
        }

        // Rango de fechas sobre la fecha efectiva. Los items sin fecha (tareas) quedan
        // fuera cuando hay rango activo → la rama B ni entra al UNION en ese caso.
        $conRango = (bool) ($desde || $hasta);
        if ($desde) $proyectos->whereRaw('DATE(COALESCE(fecha_reprogramacion, fecha_limite)) >= ?', [$desde]);
        if ($hasta) $proyectos->whereRaw('DATE(COALESCE(fecha_reprogramacion, fecha_limite)) <= ?', [$hasta]);

        $union = $conRango ? $proyectos : $proyectos->unionAll($tareas);
        $base  = DB::query()->fromSub($union, 'mp');

        // Totales del conjunto filtrado (agregados en SQL, sin traer filas a PHP).
        $total    = (clone $base)->count();
        $vencidas = (clone $base)
            ->whereNotNull('due_date')->where('due_date', '<', $hoy)
            ->whereNotIn('status', $terminales)
            ->count();

        // Orden — 'fecha': vencidas primero, luego fecha ascendente (sin fecha al final);
        // 'prioridad': Alta→Media→Baja y dentro, vencidas y fecha. `key` como desempate
        // determinista para que la paginación sea estable entre requests.
        $ph   = implode(',', array_fill(0, count($terminales), '?'));
        $rows = (clone $base)
            ->selectRaw("mp.*,
                (due_date IS NOT NULL AND due_date < ? AND status NOT IN ($ph)) AS is_overdue,
                (status IN ($ph)) AS is_terminal,
                CASE LOWER(priority) WHEN 'alta' THEN 0 WHEN 'media' THEN 1 WHEN 'baja' THEN 2 ELSE 9 END AS peso",
                [$hoy, ...$terminales, ...$terminales])
            ->when($sort === 'prioridad', fn ($qq) => $qq->orderBy('peso'))
            ->orderByDesc('is_overdue')
            ->orderByRaw("COALESCE(due_date, '9999-12-31') ASC")
            ->orderBy('key')
            ->forPage($page, $perPage)
            ->get();

        // Hidratación SOLO de la página actual: creadores y nombres de espacio (2 queries).
        $users = \Modules\User\Models\User::whereIn('id', $rows->pluck('creator_id')->filter()->unique())
            ->get(['id', 'name', 'avatar'])->keyBy('id');
        $names = GpProject::whereIn('key', $rows->pluck('project')->filter()->unique())
            ->pluck('name', 'key');

        $pageItems = $rows->map(function ($r) use ($users, $names) {
            $creator = $users[$r->creator_id] ?? null;
            return [
                'key'          => $r->key,
                'type'         => $r->src === 't' ? 'tarea' : ($r->reprogramacion_root_key ? 'reprogramacion' : ($r->parent_key ? 'subactividad' : 'actividad')),
                'title'        => $r->title,
                'status'       => $r->status,
                'priority'     => $r->priority,
                'project'      => $r->project,
                'project_name' => $names[$r->project] ?? $r->project,
                'parent_key'   => $r->reprogramacion_root_key ?: $r->parent_key,
                'due_date'     => $r->due_date,
                'is_overdue'   => (bool) $r->is_overdue,
                'is_terminal'  => (bool) $r->is_terminal,
                'creator'      => $creator ? ['display_name' => $creator->name, 'avatar_url' => $creator->avatar_url] : null,
            ];
        })->values()->all();

        return response()->json([
            'items'    => $pageItems,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'vencidas' => $vencidas,
            'has_more' => $page * $perPage < $total,
        ]);
    }

    /**
     * GET /member-candidates — usuarios elegibles para ser miembros de un espacio.
     * Requiere gestion-proyectos.miembro (o admin) + gestion-proyectos.ver.
     */
    public function memberCandidates(): JsonResponse
    {
        return response()->json($this->service->getSpaceMemberCandidates());
    }

    // =========================================================================
    // issueTypes
    // =========================================================================

    public function issueTypes(Request $request): JsonResponse
    {
        return response()->json($this->service->getIssueTypes());
    }

    // =========================================================================
    // priorities
    // =========================================================================

    public function priorities(): JsonResponse
    {
        return response()->json($this->service->getPriorities());
    }

    // =========================================================================
    // statuses
    // =========================================================================

    public function statuses(Request $request): JsonResponse
    {
        return response()->json($this->service->getStatuses());
    }

    // =========================================================================
    // labels (legacy endpoint — now returns labels of active project)
    // =========================================================================

    public function labels(Request $request): JsonResponse
    {
        $projectKey = (string) $request->query('project', '');
        if ($projectKey) {
            $project = GpProject::where('key', $projectKey)->with('labels')->first();
            return response()->json(
                $project ? $project->labels->pluck('name')->values() : []
            );
        }
        return response()->json($this->service->getLabels());
    }

    // =========================================================================
    // bulkUpdate
    // =========================================================================

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys'                => ['required', 'array', 'min:1', 'max:50'],
            'keys.*'              => ['required', 'string', 'max:30'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'assignee_account_id' => ['nullable', 'string', 'max:30'],
            'fecha_limite'        => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'label_ids'           => ['nullable', 'array'],
            'label_ids.*'         => ['integer', 'exists:gp_labels,id'],
        ]);

        $keys   = $validated['keys'];

        // Permiso: edición masiva = propietario + administrador + implementador + admin
        // global (misma regla que la edición inline de datos). Vive dentro de un espacio;
        // autorizar el primer proyecto lo representa. El service mantiene su can('update')
        // por ítem; este gate corre primero.
        $first = Proyecto::whereIn('key', $keys)->first();
        if ($first) {
            $this->authorize('inlineEdit', $first);
        }

        $fields = [];

        if (!empty($validated['status']))              $fields['status']    = $validated['status'];
        if (!empty($validated['priority']))            $fields['priority']     = $validated['priority'];
        if (!empty($validated['fecha_limite']))        $fields['fecha_limite'] = $validated['fecha_limite'];
        if (!empty($validated['labels']))              $fields['labels']       = $validated['labels'];
        if (!empty($validated['assignee_account_id'])) {
            $fields['assignee_id'] = $this->resolveUserId($validated['assignee_account_id']);
        }

        $labelIds = $validated['label_ids'] ?? null;

        $result = $this->service->bulkUpdate($keys, $fields, $labelIds, auth()->user());

        return response()->json($result);
    }

    // =========================================================================
    // bulkDelete
    // =========================================================================

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys'   => ['required', 'array', 'min:1', 'max:50'],
            'keys.*' => ['required', 'string', 'max:30'],
        ]);

        $result = $this->service->bulkDelete($validated['keys'], auth()->user());

        return response()->json($result);
    }

    // =========================================================================
    // Custom Fields
    // =========================================================================

    /** GET /projects/{projectKey}/custom-fields */
    public function getCustomFields(string $projectKey): JsonResponse
    {
        GpProject::where('key', $projectKey)->firstOrFail();
        return response()->json($this->customFieldService->getCustomFields($projectKey));
    }

    /** POST /projects/{projectKey}/custom-fields */
    public function storeCustomField(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);

        GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'type'      => ['required', 'string', 'in:text,paragraph,timestamp,dropdown,date,number,labels,checkbox,people,url,select'],
            'options'   => ['nullable', 'array'],
            'options.*' => ['string', 'max:100'],
            'order'     => ['nullable', 'integer', 'min:0'],
        ]);

        $field = $this->customFieldService->addCustomField($projectKey, $validated);

        return response()->json($field->toFrontend(), 201);
    }

    /** PUT /custom-fields/{field} */
    public function updateCustomField(Request $request, GpCustomField $field): JsonResponse
    {
        $this->authorize('manage', $field->project_key);

        $validated = $request->validate([
            'name'      => ['nullable', 'string', 'max:100'],
            'type'      => ['nullable', 'string', 'in:text,paragraph,timestamp,dropdown,date,number,labels,checkbox,people,url,select'],
            'options'   => ['nullable', 'array'],
            'options.*' => ['string', 'max:100'],
            'order'     => ['nullable', 'integer', 'min:0'],
        ]);

        // En campos predefinidos solo se permite renombrar / reordenar.
        // Cambiar type/options podría romper los valores ya guardados.
        if ($field->is_default) {
            $validated = array_intersect_key($validated, array_flip(['name', 'order']));
        }

        $updated = $this->customFieldService->updateCustomField($field->id, $validated);

        return response()->json($updated->toFrontend());
    }

    /** DELETE /custom-fields/{field} */
    public function destroyCustomField(GpCustomField $field): JsonResponse
    {
        $this->authorize('manage', $field->project_key);

        // Los campos sembrados por defecto al crear el proyecto no pueden
        // borrarse — solo renombrarse desde la UI.
        if ($field->is_default) {
            return response()->json([
                'error' => 'Este es un campo predefinido del proyecto y no puede eliminarse.',
            ], 403);
        }

        $this->customFieldService->deleteCustomField($field->id);
        return response()->json(['message' => 'Campo eliminado.']);
    }

    // =========================================================================
    // Column Preferences
    // =========================================================================

    /** GET /column-preferences/{projectKey} */
    public function getColumnPreferences(string $projectKey): JsonResponse
    {
        return response()->json(
            $this->customFieldService->getColumnPreferences(auth()->id(), $projectKey)
        );
    }

    /** POST /column-preferences/{projectKey} */
    public function saveColumnPreferences(Request $request, string $projectKey): JsonResponse
    {
        // Escribe el default de columnas del espacio (user_id = null, compartido por todos):
        // solo propietario/administrador/admin global pueden alterar el layout común.
        $this->authorize('manage', $projectKey);

        $validated = $request->validate([
            'columns'          => ['required', 'array'],
            'columns.*.key'    => ['required', 'string', 'max:80'],
            'columns.*.name'   => ['required', 'string', 'max:100'],
            'columns.*.visible' => ['required', 'boolean'],
            'columns.*.order'  => ['required', 'integer', 'min:0'],
        ]);

        // Guarda como default del proyecto (user_id = null) — aplica a todos los usuarios
        $this->customFieldService->saveColumnPreferences($projectKey, $validated['columns']);

        return response()->json(['message' => 'Preferencias guardadas.']);
    }

    /** GET /catalog-columns */
    public function getCatalogColumns(): JsonResponse
    {
        return response()->json($this->customFieldService->getCatalogColumns());
    }

    // =========================================================================
    // Space Categories — CRUD
    // =========================================================================

    /** GET /categories — lista todas las categorías */
    public function indexCategories(): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');
        return response()->json(GpSpaceCategory::orderBy('name')->get(['id', 'name'])->values());
    }

    /** POST /categories — crea una categoría */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:gp_space_categories,name'],
        ], [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
        ]);

        $category = GpSpaceCategory::create(['name' => $validated['name']]);

        return response()->json($category, 201);
    }

    /** PUT /categories/{id} — actualiza el nombre */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $category = GpSpaceCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:gp_space_categories,name,' . $id],
        ], [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
        ]);

        $category->update(['name' => $validated['name']]);

        return response()->json($category);
    }

    /** DELETE /categories/{id} — elimina y pone space_category_id = null en proyectos */
    public function destroyCategory(int $id): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $category = GpSpaceCategory::findOrFail($id);

        // La FK tiene ON DELETE SET NULL, pero hacemos el update explícito
        // para que el caché de proyectos se invalide correctamente vía model events.
        GpProject::where('space_category_id', $id)->each(function (GpProject $p) {
            $p->update(['space_category_id' => null]);
        });

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    // =========================================================================
    // Space Labels — CRUD + sincronización proyecto ↔ etiquetas
    // =========================================================================

    /** GET /projects/{projectKey}/labels — etiquetas propias del espacio */
    public function getProjectLabels(string $projectKey): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $labels = GpLabel::forProject($projectKey)->orderBy('name')->get(['id', 'name']);
        return response()->json($labels->values());
    }

    /** POST /projects/{projectKey}/labels — crear etiqueta en el espacio */
    public function storeLabel(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);
        GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('gp_labels', 'name')->where('project_key', $projectKey),
            ],
        ], [
            'name.unique' => 'Ya existe una etiqueta con ese nombre en este espacio.',
        ]);

        $label = GpLabel::create(['project_key' => $projectKey, 'name' => $validated['name']]);
        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        return response()->json(['id' => $label->id, 'name' => $label->name], 201);
    }

    /** PUT /labels/{id} — renombrar etiqueta del espacio */
    public function updateLabel(Request $request, int $id): JsonResponse
    {
        $label = GpLabel::findOrFail($id);
        $this->authorize('manage', $label->project_key);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('gp_labels', 'name')->where('project_key', $label->project_key)->ignore($id),
            ],
        ], [
            'name.unique' => 'Ya existe una etiqueta con ese nombre en este espacio.',
        ]);

        $label->update(['name' => $validated['name']]);
        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        return response()->json(['id' => $label->id, 'name' => $label->name]);
    }

    /** DELETE /labels/{id} — eliminar etiqueta del espacio */
    public function destroyLabel(int $id): JsonResponse
    {
        $label = GpLabel::findOrFail($id);
        $this->authorize('manage', $label->project_key);

        $label->delete();
        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        return response()->json(['message' => 'Etiqueta eliminada.']);
    }

    /** POST /projects/{projectKey}/labels/inherit — copiar etiquetas de otro espacio */
    public function inheritLabels(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);
        GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'from_project_key' => ['required', 'string', 'exists:gp_projects,key', 'different:' . $projectKey],
            // Opcional: nombres específicos a heredar. Si no se envía, se heredan todas.
            'names'            => ['nullable', 'array'],
            'names.*'          => ['string', 'max:100'],
        ], [
            'from_project_key.different' => 'No puedes heredar etiquetas del mismo espacio.',
        ]);

        // Nombres ya existentes en el espacio destino (para no duplicar).
        $existing = GpLabel::forProject($projectKey)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();

        // Selección (lowercase) si el usuario eligió etiquetas específicas; null = todas.
        $selected = $request->filled('names')
            ? collect($validated['names'])->map(fn ($n) => mb_strtolower(trim($n)))->all()
            : null;

        $source = GpLabel::forProject($validated['from_project_key'])->get(['name']);
        $created = 0;
        foreach ($source as $src) {
            $lower = mb_strtolower($src->name);
            if ($selected !== null && !in_array($lower, $selected, true)) {
                continue; // no fue seleccionada
            }
            if (in_array($lower, $existing, true)) {
                continue; // ya existe, se omite
            }
            GpLabel::create(['project_key' => $projectKey, 'name' => $src->name]);
            $existing[] = $lower;
            $created++;
        }
        \Illuminate\Support\Facades\Cache::forget('gp.projects');

        return response()->json([
            'message' => "Se heredaron {$created} etiqueta(s).",
            'created' => $created,
        ]);
    }


    // =========================================================================
    // Activity History — Historial de Actividades por tarea
    // =========================================================================

    /** GET /activity/{key} — lista el historial de una tarea */
    public function getActivityHistory(string $key): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        // {key} es adivinable y el historial expone comentarios y EVIDENCIA de transiciones:
        // exigir membresía vigente en el espacio, igual que indexSubTareas/indexSubActividades.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $proyecto->project), 403, 'No tienes acceso a este espacio.');

        $entries = GpActivityHistory::where('tarea_key', $key)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => $e->toFrontend())
            ->values();

        return response()->json($entries);
    }

    // =========================================================================
    // Timeline — Seguimiento de cambios (audit + actividad manual)
    // =========================================================================

    /**
     * GET /timeline/{key} — timeline unificado de una tarea.
     *
     * Combina dos fuentes:
     *  - gp_audit_log: cambios de campos críticos (creados por ProyectoObserver)
     *  - gp_activity_history: comentarios manuales con adjunto
     *
     * Devuelve un array ordenado por fecha desc listo para el frontend.
     */
    public function getTimeline(string $key): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        // {key} adivinable → el audit log de un espacio ajeno no debe ser legible.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $proyecto->project), 403, 'No tienes acceso a este espacio.');

        // Solo entradas de audit_log (cambios de campos críticos)
        // Los comentarios se muestran en el modal "Historial de Actividades"
        $timeline = GpAuditLog::where('model_key', $key)
            ->where('model_type', 'Proyecto')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => $a->toTimeline())
            ->values()
            ->all();

        return response()->json($timeline);
    }

    /**
     * GET /timeline/{key}/titulos — histórico de TÍTULOS (solo cambios de `summary`),
     * paginado de 10 en 10. Filtra en BD a las entradas cuyo cambio incluye summary
     * (no trae todo el timeline), y devuelve solo el viejo→nuevo del título.
     */
    public function getTitleHistory(Request $request, string $key): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');
        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        // {key} adivinable → el histórico de títulos de un espacio ajeno no debe ser legible.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $proyecto->project), 403, 'No tienes acceso a este espacio.');

        $perPage = 10;
        $page    = max(1, (int) $request->query('page', 1));

        $base = GpAuditLog::where('model_key', $key)
            ->where('model_type', 'Proyecto')
            // Solo entradas donde el título realmente cambió (el observer escribe solo
            // los campos modificados, así que basta con que summary exista en new_values).
            ->whereRaw("JSON_CONTAINS_PATH(new_values, 'one', '$.summary')")
            ->orderByDesc('created_at');

        $total = (clone $base)->count();
        $items = $base->forPage($page, $perPage)->get()->map(function (GpAuditLog $a) {
            $old = $a->old_values ?? [];
            $new = $a->new_values ?? [];

            return [
                'date' => $a->created_at?->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
                'user' => $a->user_name ?? 'Sistema',
                'old'  => GpAuditLog::formatValue('summary', $old['summary'] ?? null),
                'new'  => GpAuditLog::formatValue('summary', $new['summary'] ?? null),
            ];
        })->all();

        return response()->json([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'has_more' => $page * $perPage < $total,
        ]);
    }

    /** POST /activity/{key} — registra una entrada de historial (comentario + adjunto opcional) */
    public function storeActivityHistory(Request $request, string $key): JsonResponse
    {
        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        // Desarrollador/Diseñador/Tester pueden comentar solo si la tarea es suya.
        $this->authorize('comentarPropia', $proyecto);

        $validated = $request->validate([
            'comment'      => ['required', 'string', 'max:5000'],
            'attachment'   => ['nullable', 'array', 'max:3'],
            'attachment.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip,txt'],
            'for_critical_transition' => ['nullable', 'boolean'],
            // Estado destino para el que esta entrada es evidencia (Finalizado/Reprogramado/Cancelado).
            'new_status' => ['nullable', 'string', 'max:60'],
        ], [
            'comment.required'   => 'El comentario es obligatorio para registrar en el historial.',
            'attachment.max'     => 'Máximo 3 archivos por evidencia.',
            'attachment.*.max'   => 'Cada archivo no puede superar los 20 MB.',
            'attachment.*.mimes' => 'Tipo de archivo no permitido.',
        ]);

        // Hasta 3 adjuntos: se guardan en la columna JSON `attachments`.
        $attachments = [];
        foreach ((array) $request->file('attachment', []) as $file) {
            $attachments[] = [
                'path' => $file->store('gp-activity/' . $key, 'local'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
            ];
        }

        $forCriticalTransition = $request->boolean('for_critical_transition');

        if ($forCriticalTransition) {
            \Modules\GestionProyectos\Support\CriticalTransitionContext::enter();
        }

        try {
            $entry = GpActivityHistory::create([
                'tarea_key'       => $key,
                'user_id'         => $request->user()->id,
                'comment'         => $validated['comment'],
                // Solo se etiqueta como evidencia de transición si es parte de un cambio de estado crítico.
                'new_status'      => $forCriticalTransition ? ($validated['new_status'] ?? null) : null,
                'attachments'     => $attachments ?: null,
            ]);
        } finally {
            if ($forCriticalTransition) {
                \Modules\GestionProyectos\Support\CriticalTransitionContext::leave();
            }
        }

        $entry->load('user');

        return response()->json($entry->toFrontend(), 201);
    }

    /** GET /activity/attachment/{id}/{index?} — descarga protegida de un adjunto de historial */
    public function downloadActivityAttachment(int $id, int $index = 0): mixed
    {
        $entry = GpActivityHistory::findOrFail($id);

        // Verificar que el usuario tiene acceso al proyecto de la tarea
        $proyecto = Proyecto::where('key', $entry->tarea_key)->firstOrFail();
        abort_unless(
            auth()->user()->hasRole('admin')
            || auth()->user()->can('gestion-proyectos.admin')
            || GpSpaceMember::roleInSpace(auth()->id(), $proyecto->project) !== null,
            403
        );

        $file = $this->resolveHistorialFile($entry, $index);
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name'] ?? 'adjunto');
    }

    /**
     * Devuelve el adjunto en la posición $index de una entrada de historial
     * (soporta el JSON `attachments` nuevo y el legacy de columna única), o null.
     */
    private function resolveHistorialFile($entry, int $index): ?array
    {
        $files = is_array($entry->attachments) && count($entry->attachments)
            ? $entry->attachments
            : (! empty($entry->attachment_path) ? [[
                'path' => $entry->attachment_path,
                'name' => $entry->attachment_name,
                'mime' => $entry->attachment_mime,
            ]] : []);

        return $files[$index] ?? null;
    }

    // =========================================================================
    // Sub Tareas — Blueprint §2.5
    // =========================================================================

    /** GET /subtareas/{key} — lista sub tareas de una tarea padre */
    public function indexSubTareas(string $key): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $padre = Proyecto::where('key', $key)->firstOrFail();
        // Mismo gate que indexSubActividades: {key} adivinable → exigir membresía vigente.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $padre->project), 403, 'No tienes acceso a este espacio.');

        $subs = \Modules\GestionProyectos\Models\GpSubTarea::where('parent_key', $key)
            ->with(['assignee', 'creator'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($s) => $s->toFrontend())
            ->values();

        return response()->json($subs);
    }

    /** POST /subtareas/{key} — crea una sub tarea */
    public function storeSubTarea(Request $request, string $key): JsonResponse
    {
        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        $this->authorize('crear', $proyecto->project);

        $validated = $request->validate([
            'summary'       => ['required', 'string', 'max:500'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'observacion'   => ['nullable', 'string', 'max:2000'],
            'assignee_id'   => ['nullable', 'integer', 'exists:users,id'],
            'status'        => ['nullable', 'string', 'in:Pendiente,Finalizado'],
            'priority'      => ['nullable', 'string', 'in:Alta,Media,Baja'],
        ]);

        $sub = DB::transaction(function () use ($validated, $key, $request) {
            return \Modules\GestionProyectos\Models\GpSubTarea::create(array_merge($validated, [
                'parent_key' => $key,
                'creator_id' => $request->user()->id,
                'status'     => $validated['status'] ?? 'Pendiente',
            ]));
        });

        $sub->load(['assignee', 'creator']);

        return response()->json($sub->toFrontend(), 201);
    }

    /** PATCH /subtareas/item/{id} — actualiza una sub tarea */
    public function updateSubTarea(Request $request, int $id): JsonResponse
    {
        $sub = GpSubTarea::findOrFail($id);
        // Actualizar una tarea es una acción de FLUJO, no solo de datos: escritores ∪
        // aprobadores, igual que el MCP. Con 'crear' (canWrite) el rol 'aprobador' —el
        // único que el hook de GpSubTarea nombra para finalizar— quedaba fuera por web.
        // El hook restringe al aprobador a mover solo el estado.
        $this->authorize('registrarHistorial', $sub->padre->project);

        $validated = $request->validate([
            'summary'       => ['sometimes', 'string', 'max:500'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'observacion'   => ['nullable', 'string', 'max:2000'],
            'assignee_id'   => ['nullable', 'integer', 'exists:users,id'],
            'status'        => ['nullable', 'string', 'in:Pendiente,Finalizado'],
            'priority'      => ['nullable', 'string', 'in:Alta,Media,Baja'],
        ]);

        // La regla de finalización (rol aprobador + evidencia + estado terminal)
        // vive en el modelo GpSubTarea — única fuente de verdad que cubre web y MCP.
        // Acá solo la traducimos a una respuesta JSON limpia.
        try {
            $sub->update($validated);
        } catch (GestionProyectosException $e) {
            return response()->json(
                array_merge(['error' => $e->getMessage()], $e->getContext()),
                422
            );
        }

        $sub->load(['assignee', 'creator']);

        return response()->json($sub->toFrontend());
    }

    /** GET /subtareas/item/{id}/historial — evidencia/comentarios de una tarea */
    public function indexSubTareaHistorial(int $id): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $sub = GpSubTarea::findOrFail($id);
        // {id} adivinable → exigir membresía vigente en el espacio de la actividad padre.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $sub->padre->project), 403, 'No tienes acceso a este espacio.');

        $items = GpSubTareaHistorial::where('sub_tarea_id', $sub->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($h) => $h->toFrontend())
            ->values();

        return response()->json($items);
    }

    /** POST /subtareas/item/{id}/historial — registra comentario + adjunto en una tarea */
    public function storeSubTareaHistorial(Request $request, int $id): JsonResponse
    {
        $sub = GpSubTarea::findOrFail($id);
        $this->authorize('registrarHistorial', $sub->padre->project);

        $validated = $request->validate([
            'comment'      => ['required', 'string', 'max:5000'],
            'attachment'   => ['nullable', 'array', 'max:3'],
            'attachment.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip,txt'],
        ], [
            'comment.required'   => 'El comentario es obligatorio para registrar la evidencia.',
            'attachment.max'     => 'Máximo 3 archivos por evidencia.',
            'attachment.*.max'   => 'Cada archivo no puede superar los 20 MB.',
            'attachment.*.mimes' => 'Tipo de archivo no permitido.',
        ]);

        // Hasta 3 adjuntos: se guardan en la columna JSON `attachments`.
        $attachments = [];
        foreach ((array) $request->file('attachment', []) as $file) {
            $attachments[] = [
                'path' => $file->store('gp-subtarea/' . $sub->key, 'local'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
            ];
        }

        $entry = GpSubTareaHistorial::create([
            'sub_tarea_id' => $sub->id,
            'user_id'      => $request->user()->id,
            'comment'      => $validated['comment'],
            'attachments'  => $attachments ?: null,
        ]);
        $entry->load('user');

        return response()->json($entry->toFrontend(), 201);
    }

    /** GET /subtareas/historial/attachment/{id}/{index?} — descarga un adjunto de una evidencia */
    public function downloadSubTareaAttachment(int $id, int $index = 0): mixed
    {
        $entry = GpSubTareaHistorial::findOrFail($id);
        $sub   = GpSubTarea::findOrFail($entry->sub_tarea_id);
        $proyecto = $sub->padre;

        abort_unless(
            auth()->user()->hasRole('admin')
            || auth()->user()->can('gestion-proyectos.admin')
            || GpSpaceMember::roleInSpace(auth()->id(), $proyecto->project) !== null,
            403
        );

        $file = $this->resolveHistorialFile($entry, $index);
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name'] ?? 'adjunto');
    }

    /** DELETE /subtareas/item/{id} — elimina una sub tarea */
    public function destroySubTarea(int $id): JsonResponse
    {
        $sub = \Modules\GestionProyectos\Models\GpSubTarea::findOrFail($id);
        $this->authorize('manage', $sub->padre->project);
        $sub->delete();

        return response()->json(['message' => 'Sub tarea eliminada.']);
    }

    // =========================================================================
    // Sub Actividades — Proyecto anidado (mismos campos que una Actividad)
    // =========================================================================

    /** GET /subactividades/{key} — lista las subactividades de una actividad, con cumplimiento. */
    public function indexSubActividades(string $key): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $padre = Proyecto::where('key', $key)->firstOrFail();
        // {key} es adivinable: sin este gate, cualquier usuario con .ver leía datos
        // de espacios ajenos por URL directa. Solo miembros vigentes/owner/admin global.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $padre->project), 403, 'No tienes acceso a este espacio.');

        $subs = Proyecto::where('parent_key', $key)
            ->with(['assignee', 'reporter', 'creator', 'aprobadoPor', 'validadoPor', 'team', 'customFieldValues.customField'])
            ->withCount([
                'subTareas',
                'subTareas as sub_tareas_done_count' => fn ($q) => $q->where('status', 'Finalizado'),
                // Badge de "Reprogramaciones" en la subactividad: sus versiones -Rn propias.
                'reprogramaciones',
                // Badge de "Seguimiento de cambios" en la subactividad (excluye "created").
                'auditLogs as audit_changes_count' => fn ($q) => $q->where('action', '!=', 'created'),
                // Badge de "Historial de actividades" (entradas de evidencia/comentarios).
                'activityHistory as activity_history_count',
            ])
            ->orderBy('created_at')
            ->get();

        $data = $subs->map(function (Proyecto $sub) {
            $dto   = ProyectoDTO::fromModel($sub);
            $total = (int) $sub->sub_tareas_count;
            $done  = (int) $sub->sub_tareas_done_count;
            $dto['cumplimiento'] = [
                'tareas_total' => $total,
                'tareas_done'  => $done,
                'pct'          => $total > 0 ? (int) round($done * 100 / $total) : 0,
            ];

            return $dto;
        })->values()->all();

        return response()->json($data);
    }

    /** POST /subactividades/{key} — crea una subactividad anidada en una actividad. */
    public function storeSubActividad(Request $request, string $key): JsonResponse
    {
        $padre = Proyecto::where('key', $key)->firstOrFail();

        // Una subactividad solo puede colgar de una ACTIVIDAD top-level (1 nivel).
        if (!empty($padre->parent_key)) {
            return response()->json([
                'error' => 'Una subactividad solo puede crearse dentro de una Actividad (no de otra subactividad).',
            ], 422);
        }

        $this->authorize('crear', $padre->project);

        $validated = $request->validate([
            'summary'             => ['required', 'string', 'max:500'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'fecha_limite'        => ['nullable', 'date_format:Y-m-d'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'issue_type'          => ['nullable', 'string', 'max:60'],
            'description'         => ['nullable', 'string', 'max:10000'],
            'dias_estimados'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fecha_entrega'       => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion'    => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'impacto'             => ['nullable', 'array'],
            'impacto.*'           => ['string', Rule::in(config('gestion-proyectos.impacto_values', []))],
            'software'            => ['nullable', 'string', 'max:100'],
            'entorno'             => ['nullable', 'string', 'max:100'],
            'solicitado_por'      => ['nullable', 'string', 'max:255'],
            'categoria'           => ['nullable', 'string', 'max:100'],
            'assignee_account_id' => ['nullable', 'string', 'max:30'],
            'reporter_account_id' => ['nullable', 'string', 'max:30'],
            'team_id'             => ['nullable', 'integer', 'exists:gp_teams,id'],
        ]);

        $data = $validated;
        $data['project_key'] = $padre->project;
        $data['parent_key']  = $padre->key;
        $data['assignee_id'] = $this->resolveUserId($validated['assignee_account_id'] ?? null);
        $data['creator_id']  = $request->user()->id;
        $data['reporter_id'] = empty($validated['reporter_account_id'])
            ? $request->user()->id
            : $this->resolveUserId($validated['reporter_account_id']);
        unset($data['assignee_account_id'], $data['reporter_account_id']);

        try {
            $sub = $this->service->create($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $sub->load(['assignee', 'reporter', 'creator', 'aprobadoPor', 'validadoPor', 'team']);

        return response()->json(ProyectoDTO::fromModel($sub), 201);
    }

    private function resolveUserId(?string $accountId): ?int
    {
        if ($accountId === null || $accountId === '' || $accountId === 'null') {
            return null;
        }

        // El frontend envía (string) el ID de usuario local
        $id = (int) $accountId;
        return $id > 0 ? $id : null;
    }

    // =========================================================================
    // reprogramar — crea la versión sucesora (-Rn) de una actividad
    // =========================================================================

    public function reprogramar(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'motivo'          => ['required', 'string', 'min:10', 'max:2000'],
            // Datos propios del plan sucesor
            'summary'         => ['required', 'string', 'max:500'],
            'description'     => ['nullable', 'string', 'max:10000'],
            'status'          => ['nullable', 'string', Rule::in(['Pendiente', 'En Revisión'])],
            'priority'        => ['nullable', 'string', 'max:30'],
            'assignee_account_id' => ['nullable', 'string', 'max:30'],
            'nueva_inicio'    => ['required', 'date_format:Y-m-d'],
            'nueva_limite'    => ['required', 'date_format:Y-m-d', 'after_or_equal:nueva_inicio'],
            'dias_estimados'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $proyecto = Proyecto::where('key', $key)->firstOrFail();
        $this->authorize('reprogramar', $proyecto);

        try {
            $sucesor = $this->service->reprogramar(
                $key,
                $validated['motivo'],
                [
                    'summary'        => $validated['summary'],
                    'description'    => $validated['description'] ?? null,
                    'status'         => $validated['status'] ?? 'Pendiente',
                    'priority'       => $validated['priority'] ?? null,
                    'assignee_id'    => $this->resolveUserId($validated['assignee_account_id'] ?? null),
                    'start_date'     => $validated['nueva_inicio'],
                    'fecha_limite'   => $validated['nueva_limite'],
                    'dias_estimados' => $validated['dias_estimados'] ?? null,
                ],
            );

            return response()->json([
                'message'  => "Actividad {$key} reprogramada. Nueva versión: {$sucesor->key}.",
                'sucesor'  => ProyectoDTO::fromModel($sucesor),
            ]);
        } catch (GestionProyectosException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // analytics — KPIs del espacio (Plazos + Estados). Solo gestores del espacio.
    // =========================================================================

    public function analytics(string $projectKey): JsonResponse
    {
        GpProject::where('key', $projectKey)->firstOrFail();
        // Solo propietario/administrador del espacio o admin global.
        $this->authorize('manage', $projectKey);

        return response()->json($this->service->getEspacioKpis($projectKey));
    }

    // =========================================================================
    // getReprogramaciones — lista las versiones -Rn de una actividad raíz
    // =========================================================================

    public function getReprogramaciones(string $key): JsonResponse
    {
        $root = Proyecto::where('key', $key)->firstOrFail();
        // Mismo gate que subactividades/subtareas: {key} adivinable → membresía vigente.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $root->project), 403, 'No tienes acceso a este espacio.');

        $versiones = $this->service->getReprogramaciones($key);

        return response()->json(['data' => $versiones]);
    }
}
