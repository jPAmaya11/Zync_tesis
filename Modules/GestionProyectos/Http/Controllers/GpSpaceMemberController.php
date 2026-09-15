<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;

/**
 * Gestión de miembros y roles por espacio — Blueprint §2.1
 */
class GpSpaceMemberController extends Controller
{
    use AuthorizesRequests;

    // GET /gestion-proyectos/spaces/{projectKey}/members
    public function index(string $projectKey): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        // La lista expone nombre, EMAIL y avatar de cada miembro: solo la ven los
        // miembros vigentes del espacio (u owner/admin global), no cualquier usuario
        // del módulo con un projectKey adivinable.
        abort_unless(GpSpaceMember::canSeeProject(auth()->id(), $projectKey), 403, 'No tienes acceso a este espacio.');

        $members = GpSpaceMember::where('project_key', $projectKey)
            ->with('user')
            ->orderBy('role')
            ->get()
            ->map(fn ($m) => $m->toFrontend());

        return response()->json($members);
    }

    // POST /gestion-proyectos/spaces/{projectKey}/members
    public function store(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);

        GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'string', 'in:propietario,administrador,ejecutor,aprobador,lector,implementador'],
        ]);

        // El usuario a añadir debe tener gestion-proyectos.ver y gestion-proyectos.miembro
        $targetUser = \Modules\User\Models\User::find($validated['user_id']);
        if (!$targetUser->can('gestion-proyectos.miembro') && !$targetUser->can('gestion-proyectos.admin')) {
            return response()->json([
                'error' => 'El usuario no tiene el permiso "gestion-proyectos.miembro" requerido para ser miembro de un espacio.',
            ], 422);
        }

        // Si el rol es propietario, solo puede haber uno
        if ($validated['role'] === 'propietario') {
            $existing = GpSpaceMember::where('project_key', $projectKey)
                ->where('role', 'propietario')
                ->first();
            if ($existing) {
                return response()->json([
                    'error' => 'Ya existe un Propietario en este espacio. Transfiere el rol desde el miembro existente.',
                ], 422);
            }
        }

        // Alta manual (botón "+"): miembro independiente, NO atado a equipos.
        $member = GpSpaceMember::updateOrCreate(
            ['project_key' => $projectKey, 'user_id' => $validated['user_id']],
            ['role' => $validated['role'], 'team_synced' => false]
        );

        $member->load('user');

        return response()->json($member->toFrontend(), 201);
    }

    // PATCH /gestion-proyectos/spaces/member/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $member = GpSpaceMember::findOrFail($id);
        $this->authorize('manage', $member->project_key);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:propietario,administrador,ejecutor,aprobador,lector,implementador'],
        ]);

        // Si el nuevo rol es propietario, solo puede haber uno
        if ($validated['role'] === 'propietario') {
            $existing = GpSpaceMember::where('project_key', $member->project_key)
                ->where('role', 'propietario')
                ->where('id', '!=', $id)
                ->first();
            if ($existing) {
                return response()->json([
                    'error' => 'Ya existe un Propietario. Cambia el rol del actual Propietario primero.',
                ], 422);
            }
        }

        // El rol se cambia SIN alterar el origen de la membresía: `team_synced` sigue
        // indicando si el miembro vino de un equipo. Así, si luego se borra/quita ese equipo
        // (y no queda en ningún otro), syncMembersFromTeams le revoca el acceso aunque le
        // hayan cambiado el rol. Solo los miembros agregados manualmente (team_synced=false)
        // persisten independientemente de los equipos.
        $member->role = $validated['role'];
        $member->save();
        $member->load('user');

        return response()->json($member->toFrontend());
    }

    // DELETE /gestion-proyectos/spaces/member/{id}
    public function destroy(int $id): JsonResponse
    {
        $member = GpSpaceMember::findOrFail($id);
        $this->authorize('manage', $member->project_key);

        if ($member->role === 'propietario') {
            return response()->json([
                'error' => 'No se puede eliminar al Propietario. Transfiere el rol primero.',
            ], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Miembro eliminado del espacio.']);
    }
}
