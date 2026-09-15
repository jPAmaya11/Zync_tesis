<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;

class GpDashboardController extends Controller
{
    use AuthorizesRequests;

    /** Cuántos ítems se listan en cada panel del dashboard. */
    private const LIMITE_LISTA = 8;

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('gestion-proyectos.ver');

        $user       = $request->user();
        $terminales = config('gestion-proyectos.terminal_statuses', ['Finalizado', 'Cancelado']);
        $hoy        = now()->toDateString();
        $limite     = now()->addDays(7)->toDateString();

        // null = admin global (ve todo); array = solo espacios donde es miembro vigente.
        $visibleKeys = GpSpaceMember::visibleProjectKeys($user);

        $misTareas = fn () => $this->baseMisTareas($user->id, $terminales, $visibleKeys);
        $espacios  = $this->espacios($visibleKeys);

        $porEstado = $misTareas()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $porVencer = $misTareas()
            ->whereRaw('DATE(COALESCE(fecha_reprogramacion, fecha_limite)) BETWEEN ? AND ?', [$hoy, $limite])
            ->orderByRaw('COALESCE(fecha_reprogramacion, fecha_limite) ASC')
            ->limit(self::LIMITE_LISTA)
            ->get();

        $vencidas = $misTareas()
            ->whereRaw('DATE(COALESCE(fecha_reprogramacion, fecha_limite)) < ?', [$hoy])
            ->orderByRaw('COALESCE(fecha_reprogramacion, fecha_limite) ASC')
            ->limit(self::LIMITE_LISTA)
            ->get();

        return Inertia::render('GestionProyectos/Dashboard', [
            'resumen' => [
                'activas'    => (int) $porEstado->sum(),
                'por_vencer' => $this->contar($misTareas(), 'DATE(COALESCE(fecha_reprogramacion, fecha_limite)) BETWEEN ? AND ?', [$hoy, $limite]),
                'vencidas'   => $this->contar($misTareas(), 'DATE(COALESCE(fecha_reprogramacion, fecha_limite)) < ?', [$hoy]),
                'espacios'   => $espacios->count(),
            ],
            'porEstado'          => $porEstado,
            'porVencer'          => $porVencer->map(fn ($t) => $this->tareaAFrontend($t)),
            'vencidas'           => $vencidas->map(fn ($t) => $this->tareaAFrontend($t)),
            'espacios'           => $espacios->map(fn ($e) => [
                'key'  => $e->key,
                'name' => $e->name,
                'icon' => $e->icon,
            ])->values(),
            'actividadReciente'  => $this->actividadReciente($visibleKeys),
        ]);
    }

    /**
     * Tareas activas asignadas al usuario, con la misma semántica que "Mis pendientes":
     * solo actividades vivas (no terminales), 'Reprogramado' excluido porque su plan
     * vigente es la versión -Rn, que ya aparece como ítem propio.
     */
    private function baseMisTareas(int $userId, array $terminales, ?array $visibleKeys)
    {
        $query = DB::table('gp_proyectos')
            ->where('assignee_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $terminales)
            ->where('status', '<>', 'Reprogramado');

        if ($visibleKeys !== null) {
            $query->whereIn('project', $visibleKeys);
        }

        return $query;
    }

    private function contar($query, string $whereRaw, array $bindings): int
    {
        return (int) $query->whereRaw($whereRaw, $bindings)->count();
    }

    private function espacios(?array $visibleKeys)
    {
        $query = GpProject::query()->where('active', true);

        if ($visibleKeys !== null) {
            $query->whereIn('key', $visibleKeys);
        }

        return $query->orderBy('name')->get(['key', 'name', 'icon']);
    }

    private function tareaAFrontend($tarea): array
    {
        return [
            'key'      => $tarea->key,
            'summary'  => $tarea->summary,
            'status'   => $tarea->status,
            'priority' => $tarea->priority,
            'project'  => $tarea->project,
            'due_date' => $tarea->fecha_reprogramacion ?: $tarea->fecha_limite,
        ];
    }

    /**
     * Últimos movimientos registrados en los espacios visibles. El join a gp_proyectos
     * aporta el espacio de cada tarea y descarta historial de tareas ya borradas.
     */
    private function actividadReciente(?array $visibleKeys)
    {
        $query = GpActivityHistory::query()
            ->join('gp_proyectos AS p', function ($join) {
                $join->on('p.key', '=', 'gp_activity_history.tarea_key')->whereNull('p.deleted_at');
            })
            ->with('user:id,name,avatar');

        if ($visibleKeys !== null) {
            $query->whereIn('p.project', $visibleKeys);
        }

        return $query
            ->orderByDesc('gp_activity_history.created_at')
            ->limit(self::LIMITE_LISTA)
            ->get(['gp_activity_history.*', 'p.summary AS tarea_summary', 'p.project AS tarea_project'])
            ->map(fn ($h) => [
                'id'         => $h->id,
                'tarea_key'  => $h->tarea_key,
                'tarea'      => $h->tarea_summary,
                'project'    => $h->tarea_project,
                'comment'    => $h->comment,
                'new_status' => $h->new_status,
                'usuario'    => $h->user?->name,
                'avatar_url' => $h->user?->avatar_url,
                'created_at' => optional($h->created_at)->toIso8601String(),
            ]);
    }
}
