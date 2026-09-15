<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpTeam;

/**
 * CRUD de Equipos por espacio.
 *
 * Requiere permiso: equipos.crear / equipos.ver / equipos.eliminar
 */
class GpTeamController extends Controller
{
    use AuthorizesRequests;

    // GET /gestion-proyectos/teams/{projectKey}
    public function index(string $projectKey): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $project = GpProject::where('key', $projectKey)->firstOrFail();
        
        $teams = $project->teams()
            ->with('members')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => $t->toFrontend());

        return response()->json($teams);
    }

    // GET /gestion-proyectos/teams-all
    public function globalIndex(): JsonResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $teams = GpTeam::with('members')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => $t->toFrontend());

        return response()->json($teams);
    }

    // POST /gestion-proyectos/teams/{projectKey}
    public function store(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);

        $project = GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids'  => ['nullable', 'array'],
            'member_ids.*'=> ['integer', 'exists:users,id'],
        ]);

        $team = GpTeam::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        if (!empty($validated['member_ids'])) {
            $team->members()->sync($validated['member_ids']);
        }

        // Vincular al proyecto actual sólo si el usuario activó el toggle (default true por retrocompat).
        if ($request->boolean('is_in_project', true)) {
            $project->teams()->attach($team->id);
            $project->syncMembersFromTeams();
            Cache::forget('gp.projects');
        }

        $team->load('members');

        return response()->json($team->toFrontend(), 201);
    }

    // POST /gestion-proyectos/teams-global
    public function globalStore(Request $request): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids'  => ['nullable', 'array'],
            'member_ids.*'=> ['integer', 'exists:users,id'],
        ]);

        $team = GpTeam::create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        if (!empty($validated['member_ids'])) {
            $team->members()->sync($validated['member_ids']);
        }

        return response()->json($team->load('members')->toFrontend(), 201);
    }

    // POST /gestion-proyectos/projects/{projectKey}/teams/sync
    public function syncWithProject(Request $request, string $projectKey): JsonResponse
    {
        $this->authorize('manage', $projectKey);

        $project = GpProject::where('key', $projectKey)->firstOrFail();

        $validated = $request->validate([
            'team_ids'   => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:gp_teams,id'],
        ]);

        // No permitir ASIGNAR equipos inactivos; los inactivos ya asignados pueden permanecer.
        $solicitados = $validated['team_ids'] ?? [];
        if (!empty($solicitados)) {
            $inactivos = GpTeam::whereIn('id', $solicitados)->where('is_active', false)->pluck('id')->all();
            if (!empty($inactivos)) {
                $yaAsignados     = $project->teams()->pluck('gp_teams.id')->all();
                $nuevosInactivos = array_values(array_diff($inactivos, $yaAsignados));
                if (!empty($nuevosInactivos)) {
                    return response()->json([
                        'error' => 'No se pueden asignar equipos inactivos. Reactivá el equipo antes de asignarlo.',
                    ], 422);
                }
            }
        }

        $project->teams()->sync($solicitados);

        // Sincronizar miembros como Lectores
        $project->syncMembersFromTeams();

        Cache::forget('gp.projects');

        return response()->json(['message' => 'Equipos sincronizados con el espacio.']);
    }

    // PATCH /gestion-proyectos/teams/item/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $team = GpTeam::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
            'member_ids'  => ['nullable', 'array'],
            'member_ids.*'=> ['integer', 'exists:users,id'],
        ]);

        if (isset($validated['name']))        $team->name        = $validated['name'];
        if (array_key_exists('description', $validated)) $team->description = $validated['description'];
        if (array_key_exists('is_active', $validated))   $team->is_active   = $validated['is_active'];
        $team->save();

        if (array_key_exists('member_ids', $validated)) {
            $team->members()->sync($validated['member_ids'] ?? []);

            // Sincronizar con todos los proyectos donde esté este equipo
            foreach ($team->projects as $project) {
                $project->syncMembersFromTeams();
            }
        }

        $team->load('members');

        // El nombre del equipo viaja en el cache de proyectos (toFrontend.teams[].name).
        Cache::forget('gp.projects');

        return response()->json($team->toFrontend());
    }

    // PATCH /gestion-proyectos/teams/item/{id}/active — activar/desactivar equipo
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        // Mismo permiso que editar/eliminar equipo.
        $this->authorize('gestion-proyectos.admin');

        $team = GpTeam::findOrFail($id);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $team->is_active = $validated['is_active'];
        $team->save();

        // "Puerta cerrada" reversible: al desactivar, los miembros que accedían al espacio
        // POR este equipo (team_synced) se SUSPENDEN (rol preservado); al reactivar se
        // des-suspenden. No toca a miembros manuales ni al propietario, ni las asignaciones
        // de actividades (gp_proyectos.team_id) ni el vínculo equipo↔espacio.
        foreach ($team->projects as $project) {
            $project->syncMembersFromTeams();
        }

        Cache::forget('gp.projects');

        $team->load('members');

        return response()->json($team->toFrontend());
    }

    // DELETE /gestion-proyectos/teams/item/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $team = GpTeam::with('projects')->findOrFail($id);

        // Sincronizar miembros antes de eliminar el equipo para que
        // los usuarios que solo tenían acceso por este equipo sean depurados.
        $projects = $team->projects;
        $team->delete();

        foreach ($projects as $project) {
            $project->syncMembersFromTeams();
        }

        Cache::forget('gp.projects');

        return response()->json(['message' => 'Equipo eliminado.']);
    }
}
