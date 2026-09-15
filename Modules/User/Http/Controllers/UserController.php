<?php

namespace Modules\User\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\User\Models\User;
use Modules\User\Services\UserService;
use Modules\User\Http\Requests\UserRequest;
use Modules\Role\Models\Role;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Http\Traits\AlertResponseTrait;
use Modules\User\Http\Traits\UserConfigurationTrait;


class UserController extends Controller
{
    use AlertResponseTrait, UserConfigurationTrait;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 20);

        // Obtener usuarios con filtros aplicados
        $usersPaginated = $this->userService->getUsersPaginatedWithEffectiveData(
            $request,
            $perPage,
            fn($query) => $this->applyFilters($query, $request)
        );

        $formData = $this->userService->getFormData();
        $estadisticas = $this->userService->getEstadisticas();

        // El selector ofrece todos los roles EXCEPTO "admin" cuando el actor no es
        // admin: solo un admin puede asignar el rol admin (validado en backend, ver
        // UserRequest). El resto de roles sí los puede asignar.
        $rolesAsignables = $formData['roles'];
        if (! optional($request->user())->hasRole('admin')) {
            $rolesAsignables = $rolesAsignables->reject(fn ($r) => $r->name === 'admin')->values();
        }

        // Configuración centralizada para FiltroUltra
        $configuracion = $this->getConfiguration($request);

        return Inertia::render('GestionarUsuarios', [
            'users' => $usersPaginated['data'],
            'pagination' => $usersPaginated['meta'],
            'roles' => $rolesAsignables,
            'estadisticas' => $estadisticas,
            'configuracion' => $configuracion,
            'departamentos' => $this->getDepartamentos(),
            // Usuarios que han creado a otros (opciones del filtro "Creado por")
            'creadores' => User::whereIn(
                'id',
                User::whereNotNull('created_by')->distinct()->pluck('created_by')
            )->orderBy('name')->get(['id', 'name']),
            'filtrosActivos' => [
                'search'       => $request->input('search', ''),
                'search_campo' => $request->input('search_campo', ''),
                'role_id'      => $request->input('role_id', ''),
                'department'   => $request->input('department', ''),
                'active'       => $request->input('active', ''),
                'created_by'   => $request->input('created_by', ''),
                'date_type'    => $request->input('date_type', 'created_at'),
                'date_from'    => $request->input('date_from', ''),
                'date_to'      => $request->input('date_to', ''),
            ],
        ]);
    }

    public function store(UserRequest $request)
    {
        try {
            $user = $this->userService->createUser($request->validated());
            return $this->redirectWithSuccess('Usuario creado exitosamente', 'users.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al crear usuario', $e);
        }
    }

    public function update(UserRequest $request, User $user)
    {
        try {
            $this->userService->updateUser($user, $request->validated());
            return $this->redirectWithSuccess('Usuario actualizado exitosamente', 'users.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al actualizar usuario', $e);
        }
    }

    public function destroy(User $user)
    {
        try {
            $this->userService->deleteUser($user);
            return $this->redirectWithSuccess('Usuario eliminado exitosamente', 'users.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al eliminar usuario', $e);
        }
    }

    public function toggleStatus(User $user)
    {
        try {
            $this->userService->toggleUserStatus($user);
            return $this->redirectWithSuccess('Estado del usuario actualizado exitosamente', 'users.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al cambiar estado del usuario', $e);
        }
    }

    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password)
            ]);
            return $this->redirectWithSuccess('Contraseña actualizada exitosamente', 'users.index');
        } catch (\Exception $e) {
            return $this->redirectWithError('Error al actualizar contraseña', $e);
        }
    }

    public function estadisticas()
    {
        try {
            $stats = $this->userService->getUserStatistics();
            return $this->jsonSuccess('Estadísticas obtenidas', $stats);
        } catch (\Exception $e) {
            return $this->jsonError('Error al obtener estadísticas', [], 500);
        }
    }
}
