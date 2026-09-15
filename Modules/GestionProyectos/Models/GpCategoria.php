<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Categoría de actividad, propia de cada espacio.
 *
 * Misma filosofía que GpLabel: el catálogo NO se mantiene a mano, crece solo. La primera
 * vez alguien escribe la categoría al crear o editar una actividad y el servicio la da de
 * alta al vuelo (ver ProyectoService::ensureCategoria); a partir de ahí queda disponible
 * en el desplegable de ese espacio.
 *
 * OJO — no confundir con GpSpaceCategory, que categoriza los ESPACIOS entre sí. Esta
 * categoriza las ACTIVIDADES dentro de un espacio.
 */
class GpCategoria extends Model
{
    protected $table = 'gp_categorias';

    protected $fillable = ['project_key', 'name'];

    /** Espacio (GpProject) al que pertenece esta categoría. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GpProject::class, 'project_key', 'key');
    }

    public function scopeForProject($query, string $projectKey)
    {
        return $query->where('project_key', $projectKey);
    }
}
