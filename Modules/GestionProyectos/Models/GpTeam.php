<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Models\User;

/**
 * Equipo de un espacio — Blueprint §2.2 columna "Equipo"
 *
 * Un equipo pertenece a un único espacio (project_key) y puede tener
 * múltiples usuarios como miembros.
 */
class GpTeam extends Model
{
    use SoftDeletes;

    protected $table = 'gp_teams';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Scopes ─────────────────────────────────────────────────────────────

    /** Solo equipos operativos (asignables). */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Solo equipos archivados. */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // ─── Relaciones ─────────────────────────────────────────────────────────

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            GpProject::class,
            'gp_project_teams',
            'team_id',
            'project_key',
            'id',
            'key'
        )->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gp_team_members', 'team_id', 'user_id')
                    ->withTimestamps();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function toFrontend(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_active'    => (bool) $this->is_active,
            'created_by'   => $this->created_by,
            'members'      => $this->relationLoaded('members')
                ? $this->members->map(fn ($u) => [
                    'id'           => $u->id,
                    'account_id'   => (string) $u->id,
                    'display_name' => $u->name,
                    'avatar_url'   => $u->avatar_url,
                ])->values()->all()
                : [],
            'member_count' => $this->relationLoaded('members')
                ? $this->members->count()
                : 0,
        ];
    }
}
