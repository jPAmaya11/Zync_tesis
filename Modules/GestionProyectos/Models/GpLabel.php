<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpLabel extends Model
{
    protected $table = 'gp_labels';

    protected $fillable = ['project_key', 'name'];

    /** Espacio (GpProject) al que pertenece esta etiqueta. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GpProject::class, 'project_key', 'key');
    }

    public function scopeForProject($query, string $projectKey)
    {
        return $query->where('project_key', $projectKey);
    }
}
