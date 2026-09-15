<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpLabel;
use Modules\GestionProyectos\Models\GpSpaceCategory;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\GpTeam;
use Modules\User\Models\User;

class GpProject extends Model
{
    use SoftDeletes;

    protected $table = 'gp_projects';

    protected $fillable = [
        'key',
        'name',
        'description',
        'space_type',
        'prefix',
        'categoria',
        'space_category_id',
        'icon',
        'settings',
        'active',
        'validar_fechas_inicio',
        'produccion_editable_finalizado',
        'owner_id',
        'assignee_id',
    ];

    protected $casts = [
        'settings'                       => 'array',
        'active'                         => 'boolean',
        'validar_fechas_inicio'          => 'boolean',
        'produccion_editable_finalizado' => 'boolean',
    ];

    // Caché de proyectos: se invalida en cualquier delete (soft o force) y en save/restore.
    // El cleanup físico de pivots/miembros/labels SOLO ocurre en force-delete, porque
    // un soft-delete debe ser reversible vía ->restore() sin perder hijos.
    // El purge de storeProject usa forceDelete() → dispara forceDeleted → cascade corre.
    protected static function booted(): void
    {
        $flush = fn () => Cache::forget('gp.projects');
        static::saved($flush);
        static::restored($flush);
        static::deleted($flush);
        static::forceDeleted(function (GpProject $project) use ($flush) {
            DB::table('gp_project_teams')->where('project_key', $project->key)->delete();
            DB::table('gp_space_members')->where('project_key', $project->key)->delete();
            // gp_labels se borra solo por la FK cascade de project_key.
            $flush();
        });
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /** Todas las tareas/issues de este proyecto. */
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'project', 'key');
    }

    /** Campos personalizados del espacio. */
    public function customFields(): HasMany
    {
        return $this->hasMany(GpCustomField::class, 'project_key', 'key');
    }

    /** Propietario del espacio. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Usuario asignado al proyecto. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** Categoría del espacio. */
    public function spaceCategory(): BelongsTo
    {
        return $this->belongsTo(GpSpaceCategory::class, 'space_category_id');
    }

    /** Miembros del espacio con su rol. */
    public function spaceMembers(): HasMany
    {
        return $this->hasMany(GpSpaceMember::class, 'project_key', 'key');
    }

    /** Equipos asociados a este espacio. */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            GpTeam::class,
            'gp_project_teams',
            'project_key',
            'team_id',
            'key',
            'id'
        )->withTimestamps();
    }

    /** Etiquetas propias del espacio (1:N por project_key). */
    public function labels(): HasMany
    {
        return $this->hasMany(GpLabel::class, 'project_key', 'key');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Sincroniza los miembros del espacio que provienen de equipos (team_synced).
     *
     * Distingue equipos ACTIVOS de INACTIVOS para implementar la "puerta cerrada"
     * reversible de la desactivación de equipos:
     *  - Equipo ACTIVO   → sus miembros entran (lector si son nuevos) y se DES-SUSPENDEN.
     *  - Equipo INACTIVO (y es el único vínculo del user a este espacio) → se SUSPENDE
     *    su membresía: la fila se conserva con su rol intacto, pero no concede acceso.
     *    Al reactivar el equipo se des-suspende y queda "como antes".
     *  - Sin NINGÚN equipo que lo vincule (equipo desasignado/eliminado) → se ELIMINA.
     *
     * Nunca toca a los miembros manuales (team_synced=false) ni al propietario.
     */
    public function syncMembersFromTeams(): void
    {
        $teams = $this->teams()->with('members:id')->get();

        $idsDe = fn ($collection) => $collection
            ->flatMap(fn ($team) => $team->members->pluck('id'))
            ->unique()->values()->all();

        // Miembros de equipos ACTIVOS (deben tener acceso) y de TODOS los equipos.
        $activeUserIds = $idsDe($teams->filter(fn ($t) => $t->is_active));
        $allUserIds    = $idsDe($teams);
        // Vinculados SOLO por equipos inactivos → suspender (preservando rol).
        $suspendUserIds = array_values(array_diff($allUserIds, $activeUserIds));

        // 1. Alta de miembros de equipos activos que aún no son miembros (lector, sin suspender).
        //    Si ya existen (manual o previo sync), NO se modifica su rol ni su flag.
        foreach ($activeUserIds as $userId) {
            GpSpaceMember::firstOrCreate(
                ['project_key' => $this->key, 'user_id' => $userId],
                ['role' => 'lector', 'team_synced' => true, 'suspended' => false]
            );
        }

        // 2. Des-suspender a los venidos-de-equipo que vuelven a tener un equipo activo aquí
        //    (reactivación). Su rol se conserva intacto → "todo como antes".
        if (!empty($activeUserIds)) {
            GpSpaceMember::where('project_key', $this->key)
                ->where('team_synced', true)
                ->whereIn('user_id', $activeUserIds)
                ->where('suspended', true)
                ->update(['suspended' => false]);
        }

        // 3. Suspender (NO borrar) a los venidos-de-equipo cuyos equipos quedaron todos
        //    inactivos. Preserva su rol para restaurarlo al reactivar. No toca manuales.
        if (!empty($suspendUserIds)) {
            GpSpaceMember::where('project_key', $this->key)
                ->where('team_synced', true)
                ->whereIn('user_id', $suspendUserIds)
                ->update(['suspended' => true]);
        }

        // 4. Eliminar (permanente) a los venidos-de-equipo que ya no están en NINGÚN equipo
        //    del espacio (equipo desasignado o eliminado).
        GpSpaceMember::where('project_key', $this->key)
            ->where('team_synced', true)
            ->whereNotIn('user_id', $allUserIds)
            ->delete();
    }

    public function toFrontend(): array
    {
        $mapUser = fn ($u) => $u ? [
            'account_id'   => (string) $u->id,
            'display_name' => $u->name,
            'email'        => $u->email,
            // Accessor que envuelve en asset('storage/...') — devuelve URL completa
            'avatar_url'   => $u->avatar_url ?? null,
        ] : null;

        return [
            'id'                 => $this->id,
            'key'                => $this->key,
            'name'               => $this->name,
            'description'        => $this->description,
            'categoria'          => $this->categoria,
            'space_category_id'  => $this->space_category_id,
            'space_category_name'=> $this->spaceCategory?->name,
            'icon'               => $this->icon,
            'active'             => $this->active,
            // Switch por-espacio: true = bloquear fechas de inicio anteriores (hoy / padre).
            'validar_fechas_inicio' => (bool) ($this->validar_fechas_inicio ?? true),
            // Switch por-espacio: true = permitir editar "Producción" (fecha_aprobacion) aun Finalizado.
            'produccion_editable_finalizado' => (bool) ($this->produccion_editable_finalizado ?? false),
            'owner'              => $mapUser($this->owner),
            'assignee'           => $mapUser($this->assignee),
            'label_ids'          => $this->relationLoaded('labels')
                                        ? $this->labels->pluck('id')->values()->all()
                                        : [],
            'label_names'        => $this->relationLoaded('labels')
                                        ? $this->labels->pluck('name')->values()->all()
                                        : [],
            'teams'              => $this->relationLoaded('teams')
                                        ? $this->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all()
                                        : [],
        ];
    }
}
