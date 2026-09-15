<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpSpaceCategory extends Model
{
    protected $table = 'gp_space_categories';

    protected $fillable = ['name'];

    /** Proyectos que pertenecen a esta categoría. */
    public function projects(): HasMany
    {
        return $this->hasMany(GpProject::class, 'space_category_id');
    }
}
