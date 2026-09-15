<?php

namespace Modules\Role\Http\Controllers;

use Modules\Role\Models\Role;
use Modules\Role\Models\RoleAuditLog;
use Modules\Role\Services\RoleService;
use Modules\Role\Http\Requests\RoleRequest;
use App\Http\Traits\AlertResponseTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    use AlertResponseTrait;

    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    // =======================
    // MÉTODOS PRINCIPALES
    // =======================

    /**
     * Display the roles index with all roles y permisos.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $roles = $this->roleService->getRolesWithRelations($search);
        $formData = $this->roleService->getFormData();
        $estadisticas = $this->roleService->getEstadisticas();

        // Reglas para no-admin:
        //  - La LISTA de roles muestra todos excepto el rol "admin".
        //  - El ACORDEÓN de permisos muestra SOLO los permisos que el actor posee
        //    ("no puedes otorgar lo que no tienes"). El admin ve todo.
        $permissions = $formData['permissions'];
        if (! optional($request->user())->hasRole('admin')) {
            $roles = $roles->reject(fn ($r) => $r->name === 'admin')->values();

            $propios = $request->user()->getAllPermissions()->pluck('name');
            $permissions = $permissions->whereIn('name', $propios->all())->values();
        }

        // Descripciones por módulo (para qué sirve cada grupo de permisos). Se leen por
        // cualquiera que abra el modal; solo admin las edita. Mapa module_id => {body, editor, fecha}.
        $moduleComments = \Modules\Role\Models\ModuleComment::with('editor:id,name')->get()
            ->keyBy('module_id')
            ->map(fn ($c) => [
                'module_id'  => $c->module_id,
                'body'       => $c->body,
                'editor'     => $c->editor?->name,
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ]);

        return Inertia::render('GestionarRoles', [
            'roles' => $roles,
            'permissions' => $permissions,
            'moduleComments' => $moduleComments,
            'estadisticas' => $estadisticas,
            'search' => $search,
        ]);
    }

    // =======================
    // MÉTODOS CRUD
    // =======================

    /**
     * Store a newly created role in storage.
     */
    public function store(RoleRequest $request)
    {
        try {
            $this->roleService->createRole($request->validated());
            return $this->redirectWithSuccess('Rol creado correctamente', 'roles.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al crear rol', $e);
        }
    }

    /**
     * Update the specified role in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        // Solo un admin puede tocar el rol "admin".
        abort_if($role->name === 'admin' && ! optional($request->user())->hasRole('admin'), 403, 'No puedes editar el rol administrador.');

        try {
            $this->roleService->updateRole($role, $request->validated());
            return $this->redirectWithSuccess('Rol actualizado correctamente', 'roles.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al actualizar rol', $e);
        }
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        // Solo un admin puede eliminar el rol "admin".
        abort_if($role->name === 'admin' && ! optional(request()->user())->hasRole('admin'), 403, 'No puedes eliminar el rol administrador.');

        try {
            $roleName = $role->name;
            $this->roleService->deleteRole($role);
            return $this->redirectWithSuccess("Rol '{$roleName}' eliminado correctamente", 'roles.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al eliminar rol', $e);
        }
    }

    // =======================
    // MÉTODOS ADICIONALES
    // =======================

    /**
     * Auditoría del módulo de Roles (solo rol "admin"). Paginada y filtrable.
     */
    public function auditoria(Request $request)
    {
        abort_unless(optional($request->user())->hasRole('admin'), 403, 'Solo un administrador puede ver la auditoría de roles.');

        $logs = RoleAuditLog::query()
            // 'action' acepta una o varias acciones separadas por coma (los chips del filtro
            // son multi-selección). Un solo valor sigue funcionando (explode → array de uno).
            ->when($request->filled('action'),    fn ($q) => $q->whereIn('action', array_filter(explode(',', (string) $request->action))))
            ->when($request->filled('role_id'),   fn ($q) => $q->where('role_id', $request->role_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('id')
            ->paginate(20);

        $acciones = ['created', 'name_changed', 'permissions_changed', 'user_assigned', 'user_unassigned', 'deleted', 'restored'];

        return response()->json([
            'logs' => collect($logs->items())->map(fn ($l) => $l->toFrontend()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ],
            'actions' => collect($acciones)->map(fn ($a) => ['value' => $a, 'label' => RoleAuditLog::actionLabel($a)]),
        ]);
    }

    /**
     * Usuarios que tienen un rol (para el modal "Ver usuarios" de la tarjeta).
     */
    public function usuarios(Role $role)
    {
        // Coherente con el resto: solo un admin puede ver los usuarios del rol "admin".
        abort_if($role->name === 'admin' && ! optional(request()->user())->hasRole('admin'), 403, 'No puedes ver los usuarios del rol administrador.');

        // Paginado de 20 (en prod un rol puede tener 100+ usuarios). La página llega por ?page=N.
        $users = $role->users()
            ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.active')
            ->orderBy('users.name')
            ->paginate(20);

        return response()->json([
            'role'  => ['id' => $role->id, 'name' => $role->name],
            'users' => $users->items(),
            'meta'  => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'from'         => $users->firstItem(),
                'to'           => $users->lastItem(),
            ],
        ]);
    }

    /**
     * Obtener estadísticas de roles
     */
    public function estadisticas()
    {
        try {
            $estadisticas = $this->roleService->getEstadisticas();
            return $this->jsonSuccess('Estadísticas obtenidas', $estadisticas);
        } catch (\Exception $e) {
            return $this->jsonError('Error al obtener estadísticas', [], 500);
        }
    }
}
