<?php

namespace Modules\Role\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Role\Database\Factories\RoleFactory;

class Role extends SpatieRole
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles';

    /** La factory vive en el modulo, no en database/factories. */
    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    // Valor por defecto para guard_name si lo usas en la DB
    protected $attributes = [
        'guard_name' => 'web',
    ];

    // Permitir asignar 'guard_name' en mass assignment
    protected $fillable = [
        'name',
        'guard_name',
    ];
}
