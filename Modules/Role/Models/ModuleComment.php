<?php

namespace Modules\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Descripción/comentario de un MÓDULO de permisos (1:1 con Module).
 * Documenta para qué sirve el grupo de permisos. Solo el rol "admin" escribe.
 */
class ModuleComment extends Model
{
    protected $fillable = [
        'module_id',
        'body',
        'updated_by',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Último usuario que creó/editó la descripción. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
