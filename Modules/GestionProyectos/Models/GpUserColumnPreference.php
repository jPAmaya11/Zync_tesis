<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

class GpUserColumnPreference extends Model
{
    protected $table = 'gp_user_column_preferences';

    protected $fillable = [
        'user_id',
        'project_key',
        'columns',
    ];

    protected $casts = [
        'columns' => 'array',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
