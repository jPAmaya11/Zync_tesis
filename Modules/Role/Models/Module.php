<?php

namespace Modules\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /** Descripción/comentario del módulo (para qué sirve este grupo de permisos). */
    public function comment()
    {
        return $this->hasOne(ModuleComment::class);
    }
}
